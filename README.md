# Компонент 1С-Битрикс: Доступные служебные автомобили

Выводит для текущего сотрудника список свободных автомобилей на указанный интервал времени (GET: start, end).

## Импорт компонента в шаблоне через визуальный редактор
<img width="811" height="389" alt="image" src="https://github.com/user-attachments/assets/4caaa635-f6a7-4707-8eac-8bf3ffeb4f42" />

## Импорт компонента в шаблоне через PHP-код
```php
<? $APPLICATION->IncludeComponent(
	"company:free.cars",
	"", Array()
); ?>
```

Размещать в 
```bash
/local/components/
```

используем ``class.php`` вместо устаревшего формата с ``component.php``

т.к. шаблон делать не нужно - просто вывод json в
```bash
/local/components/company/free.cars/templates/.default/template.php
```

описание компонента здесь
```bash
/local/components/company/free.cars/.description.php
```

гугл док с дополнительной информацией https://docs.google.com/document/d/1LcBiObVku3xj9nxheHD_3H8TO0ufUUTfZrwxpJtmjUU/edit?tab=t.0
