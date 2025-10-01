<?php 
// если битрикс не подтянулся падаем
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = [
    "NAME"        => "Доступные служебные автомобили",
    "DESCRIPTION" => "Выводит для текущего сотрудника список свободных автомобилей на указанный интервал времени (GET: start, end).",
    "PATH"        => [
        "ID"   => "company",
        "NAME" => "Компания",
        "CHILD" => [
            "ID"   => "company_transport",
            "NAME" => "Транспорт",
        ],
    ],
    // "ICON" => "/images/icon.gif",
];