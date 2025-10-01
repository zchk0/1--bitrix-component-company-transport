<?php
// если битрикс не подтянулся падаем
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock as HL;

/**
 * Класс CompanyFreeCarsComponent
 * Битрикс-компонент для управления и отображения свободных автомобилей компании.
 */
class CompanyFreeCarsComponent extends CBitrixComponent
{
    private function getHlClassByName(string $name): string
    {
        $hl = HL\HighloadBlockTable::getList([
            'filter' => ['=NAME' => $name],
            'limit'  => 1
        ])->fetch();

        if (!$hl) {
            throw new \RuntimeException("HL-block '{$name}' not found");
        }
        $entity = HL\HighloadBlockTable::compileEntity($hl);
        return $entity->getDataClass();
    }

    private function render(array $payload, int $status = 200): void
    {
        $this->arResult = $payload;
        $this->arResult['__status'] = $status;
        $this->includeComponentTemplate();
    }

    private function escape(string $s): string
    { 
        return htmlspecialcharsbx($s);
    }

    public function executeComponent()
    {
        global $USER;

        if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
            return $this->render(['error' => 'Module highloadblock is not installed'], 500);
        }

        if (!$USER || !$USER->IsAuthorized()) {
            return $this->render(['error' => 'Unauthorized'], 401);
        }

        $request  = \Bitrix\Main\Context::getCurrent()->getRequest();
        $startStr = trim((string)$request->getQuery('start'));
        $endStr   = trim((string)$request->getQuery('end'));

        // если интервал не передан покажем все доступные автомобили для текущего пользователя
        $hasInterval = ($startStr !== '' && $endStr !== '');
        $start = null;
        $end   = null;

        if ($hasInterval) {
            try {
                $tz    = new \DateTimeZone(date_default_timezone_get());
                $start = \Bitrix\Main\Type\DateTime::createFromPhp(new \DateTime($startStr, $tz));
                $end   = \Bitrix\Main\Type\DateTime::createFromPhp(new \DateTime($endStr, $tz));
            } catch (\Throwable $e) {
                return $this->render(['error' => 'Invalid datetime format'], 400);
            }

            if (!$start || !$end || $end->getTimestamp() <= $start->getTimestamp()) {
                return $this->render(['error' => 'Invalid interval: end must be greater than start'], 400);
            }
        }

        $userId = (int)$USER->GetID();

        $rs   = \CUser::GetByID($userId);
        $user = $rs ? $rs->Fetch() : null;
        $positionId = (int)($user['UF_POSITION'] ?? 0);
        if ($positionId <= 0) {
            return $this->render(['cars' => [], 'meta' => ['reason' => 'No position assigned']], 200);
        }

        try {
            $ClassComfort     = $this->getHlClassByName('ComfortCategory');
            $ClassPosAllowed  = $this->getHlClassByName('PositionAllowedCategory');
            $ClassCarModel    = $this->getHlClassByName('CarModel');
            $ClassDriver      = $this->getHlClassByName('Driver');
            $ClassCar         = $this->getHlClassByName('Car');
            $ClassBooking     = $this->getHlClassByName('Booking');
        } catch (\Throwable $e) {
            return $this->render(['error' => $this->escape($e->getMessage())], 500);
        }

        // Разрешённые категории для позиции
        $allowedCatIds = [];
        $res = $ClassPosAllowed::getList([
            'filter' => ['=UF_POSITION' => $positionId],
            'select' => ['UF_CATEGORY']
        ]);
        while ($row = $res->fetch()) {
            $allowedCatIds[] = (int)$row['UF_CATEGORY'];
        }
        $allowedCatIds = array_values(array_unique(array_filter($allowedCatIds)));
        if (!$allowedCatIds) {
            return $this->render(['cars' => [], 'meta' => ['reason' => 'No categories allowed for position']], 200);
        }

        // Модели по разрешённым категориям
        $models = [];
        $res = $ClassCarModel::getList([
            'filter' => ['=UF_CATEGORY' => $allowedCatIds],
            'select' => ['ID', 'UF_NAME', 'UF_CATEGORY']
        ]);
        while ($m = $res->fetch()) {
            $models[(int)$m['ID']] = [
                'ID'          => (int)$m['ID'],
                'NAME'        => (string)$m['UF_NAME'],
                'CATEGORY_ID' => (int)$m['UF_CATEGORY'],
            ];
        }
        if (!$models) {
            return $this->render(['cars' => [], 'meta' => ['reason' => 'No models match allowed categories']], 200);
        }

        // Машины по найденным моделям
        $cars = [];
        $res = $ClassCar::getList([
            'filter' => ['=UF_MODEL' => array_keys($models)],
            'select' => ['ID', 'UF_REG_NUMBER', 'UF_MODEL', 'UF_DRIVER']
        ]);
        while ($c = $res->fetch()) {
            $cars[(int)$c['ID']] = [
                'ID'         => (int)$c['ID'],
                'REG_NUMBER' => (string)$c['UF_REG_NUMBER'],
                'MODEL_ID'   => (int)$c['UF_MODEL'],
                'DRIVER_ID'  => (int)$c['UF_DRIVER'],
            ];
        }
        if (!$cars) {
            return $this->render(['cars' => [], 'meta' => ['reason' => 'No cars for allowed models']], 200);
        }

        $carIds = array_keys($cars);

        // Пересечения бронирований учитываем ТОЛЬКО если задан интервал
        $busyCarIds = [];
        if ($hasInterval) {
            $res = $ClassBooking::getList([
                'filter' => [
                    '=UF_CAR'     => $carIds,
                    '<UF_START'   => $end,
                    '>UF_END'     => $start,
                    '!=UF_STATUS' => 'CANCELLED'
                ],
                'select' => ['UF_CAR']
            ]);
            while ($b = $res->fetch()) {
                $busyCarIds[(int)$b['UF_CAR']] = true;
            }
        }

        // Имена категорий
        $categoryNames = [];
        $needCatIds = array_values(array_unique(array_map(static fn($m) => $m['CATEGORY_ID'], $models)));
        if ($needCatIds) {
            $res = $ClassComfort::getList([
                'filter' => ['=ID' => $needCatIds],
                'select' => ['ID', 'UF_NAME']
            ]);
            while ($cat = $res->fetch()) {
                $categoryNames[(int)$cat['ID']] = (string)$cat['UF_NAME'];
            }
        }

        // Имена водителей
        $driverNames = [];
        $needDriverIds = array_values(array_unique(array_map(static fn($c) => (int)$c['DRIVER_ID'], $cars)));
        if ($needDriverIds) {
            $res = $ClassDriver::getList([
                'filter' => ['=ID' => $needDriverIds],
                'select' => ['ID', 'UF_NAME']
            ]);
            while ($d = $res->fetch()) {
                $driverNames[(int)$d['ID']] = (string)$d['UF_NAME'];
            }
        }

        // Формируем результат
        $free = [];
        foreach ($cars as $carId => $car) {
            // Если интервала нет — считаем все машины "доступными" по категориям.
            // Если интервал есть — исключаем занятые.
            if ($hasInterval && isset($busyCarIds[$carId])) {
                continue;
            }
            $model = $models[$car['MODEL_ID']];
            $free[] = [
                'car_id'     => $carId,
                'reg_number' => $car['REG_NUMBER'],
                'model'      => $model['NAME'],
                'comfort'    => $categoryNames[$model['CATEGORY_ID']] ?? ('#' . $model['CATEGORY_ID']),
                'driver'     => $driverNames[$car['DRIVER_ID']] ?? '',
            ];
        }

        // Отдаём данные в шаблон
        return $this->render([
            'cars' => array_values($free),
            'meta' => [
                'mode'      => $hasInterval ? 'by_interval' : 'all_available',
                'requested' => [
                    'start' => $hasInterval ? $start->toString() : null,
                    'end'   => $hasInterval ? $end->toString()   : null,
                ],
                'user_id' => $userId
            ]
        ], 200);
    }
}
