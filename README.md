# WebMoney XML API PHP

Реализация WebMoney XML API на PHP

Подробнее про интерфейсы на:

http://wiki.webmoney.ru/projects/webmoney/wiki/XML-%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81%D1%8B

Для работы нужен корневой сертификат WebMoney, который получить можно здесь:

https://cert.wmtransfer.com/regEnum/info.aspx?l=ru

Установка
---------
0. Используйте менеджер пакетов Composer для установки пакета. 

    ```
    curl -sS https://getcomposer.org/installer | php
    ```

1. И выполните установку пакета:

    ```
    php composer.phar require 'pulyavin/wmxml:~1.0' 
    ```

Использование
-------------

```php
# иницализация объекта работы с API, используя скомпилированный wmsigner
$wmxml = new pulyavin\wmxml\WMXml(
	"classic",
	[
		"wmid" => "323724870812",
		"wmsigner" => "/wmsigner/wmsigner",
		"transid" => "./wmsigner/transid.txt",
	]
);

# иницализация объекта работы с API, используя wmsigner на PHP
$wmsigner = new baibaratsky\WebMoney\Signer("323724870812", "./keyfile.kwm", "mykeypassword");

$wmxml = new pulyavin\wmxml\WMXml(
	"classic",
	[
		"wmid"     => "323724870812",
		"wmsigner" => $wmsigner,
		"transid" => "./wmsigner/transid.txt",
	]
);
```

> "transid.txt" файл, содержащий числовое значение текущей id-транзакции, которая не должна повторятся для двух разных транзакций в переделах одного wmid.
> Если вы не используете интерфейс Интерфейс X2 (Перевод средств с одного кошелька на другой), то можете не передавать этот параметр при инициализации обхекта WMXml.

```php
# переводим средства
$wmxml->xml2(
	"Z123456789122",
	"Z123456789123",
	1.23,
	"купил слона"
);
```