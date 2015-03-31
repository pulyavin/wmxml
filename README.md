# WebMoney XML API PHP

Реализация WebMoney XML API на PHP
Подробнее про интерфейсы на

http://wiki.webmoney.ru/projects/webmoney/wiki/XML-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81%D1%8B

Для работы нужен корневой сертификат WebMoney, который получить можно здесь:

https://cert.wmtransfer.com/regEnum/info.aspx?l=ru

Установка
---------
Используйте менеджер пакетов Composer для установки пакета. Создайте файл composer.json со следующими строчками (или добавьте эти строчки в имеющийся, в соответствующий блок):

```json
{
    "require": {
        "pulyavin/wmxml": "0.1"
    }
}
```

И выполните установку пакета:

```bash
$ curl -s http://getcomposer.org/installer | php
$ composer.phar install
```

Использование
---------

```php
# иницализация объекта работы с API
$wmxml = new wmxml(
	"classic",
	[
		"wmid" => "323724870812",
		"wmsigner" => "/wmsigner/wmsigner",
		"rootca" => "./WMUsedRootCAs.cer",
		"transid" => "./wmsigner/transid.txt",
	]
);

# переводим средства
$wmxml->xml2(
	"Z123456789122",
	"Z123456789123",
	1.23,
	"купил слона"
);
```