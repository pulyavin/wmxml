<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml1 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];
        $purse = $data['purse'];
        $amount = $data['amount'];
        $desc = $data['desc'];
        $orderid = $data['orderid'];
        $address = $data['address'];
        $period = $data['period'];
        $expiration = $data['expiration'];
        $onlyauth = $data['onlyauth'];
        $shop_id = $data['shop_id'];

        $reqn = $this->getReqn();
        $desc = htmlspecialchars(trim($desc), ENT_QUOTES);
        $address = htmlspecialchars(trim($address), ENT_QUOTES);
        $amount = (float)$amount;

        // ебануться, грёбанный полаллен!
        $desc_temp = mb_convert_encoding($desc, "CP1251", "UTF-8");
        $address_temp = mb_convert_encoding($address, "CP1251", "UTF-8");

        $sign = $this->keeper->getSign($orderid . $wmid . $purse . $amount . $desc_temp . $address_temp . $period . $expiration . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <invoice>
        <orderid>{$orderid}</orderid>
        <customerwmid>{$wmid}</customerwmid>
        <storepurse>{$purse}</storepurse>
        <amount>{$amount}</amount>
        <desc>{$desc}</desc>
        <address>{$address}</address>
        <period>{$period}</period>
        <expiration>{$expiration}</expiration>
        <onlyauth>{$onlyauth}</onlyauth>
        <lmi_shop_id>{$shop_id}</lmi_shop_id>
    </invoice>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'id'           => (int)$xml->invoice['id'],
            'ts'           => (int)$xml->invoice['ts'],
            'orderid'      => (int)$xml->invoice->orderid,
            'customerwmid' => (string)$xml->invoice->customerwmid,
            'storepurse'   => (string)$xml->invoice->storepurse,
            'amount'       => (float)$xml->invoice->amount,
            'desc'         => (string)$xml->invoice->desc,
            'address'      => (string)$xml->invoice->address,
            'period'       => (int)$xml->invoice->period,
            'expiration'   => (int)$xml->invoice->expiration,
            'state'        => (int)$xml->invoice->state,
            'datecrt'      => new DateTime((string)$xml->invoice->datecrt),
            'dateupd'      => new DateTime((string)$xml->invoice->dateupd),
        ];
    }

    public function getUrlClassic() {
        return "https://w3s.webmoney.ru/asp/XMLInvoice.asp";
    }

    public function getUrlLight() {
        return "https://w3s.wmtransfer.com/asp/XMLInvoiceCert.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе команды. неверный формат команды.",
            "-9"   => "неверное значение поля w3s.request/reqn",
            "-8"   => "неверное значение поля w3s.request/sign",
            "-1"   => "неверное значение поля w3s.request/invoice/orderid",
            "-2"   => "неверное значение поля w3s.request/invoice/customerwmid",
            "-3"   => "неверное значение поля w3s.request/invoice/storepurse",
            "-5"   => "неверное значение поля w3s.request/invoice/amount",
            "-6"   => "слишком длинное поле w3s.request/invoice/desc",
            "-7"   => "слишком длинное поле w3s.request/invoice/address",
            "-11"  => "идентификатор, переданный в поле w3s.request/wmid не зарегистрирован",
            "-12"  => "проверка подписи не прошла",
            "102"  => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
            "110"  => "нет прав на использования интерфейса; аттестат не удовлетворяет требованиям",
            "111"  => "попытка выставление счета для кошелька не принадлежащего WMID, которым подписывается запрос; при этом доверие не установлено.",
            "5"    => "отправитель счета не найден",
            "6"    => "получатель счета не найден",
            "7"    => "отправитель счета не найден",
            "8"    => "кошелек w3s.request/invoice/storepurse принадлежит агрегатору платежей, но lmi_shop_id не указан или указан неверно",
            "35"   => "плательщик не авторизован корреспондентом для выполнения данной операции. Это означает, что магазин пытается выписать счет плательщику, который, либо не добавил ВМИД магазина к себе в список корреспондентов и при этом запретил неавторизованным (не являющимся его корреспондентами) выписывать себе счета (для Кипер Классик - в главном меню вверху - Инструменты - Парметры программы -Ограничения ), либо плательщик добавил ВМИД магазина к себе в корреспонденты, но именно для ВМИДа этого магазина запретил выписку себе счетов. Без действий со стороны плательщика избежать этой ошибки магазин не может, необходимо показать плательщику ВМИД магазина с инструкцией о том, что ВМИД магазина должен быть добавлен плательщиком в список корреспондентов и для ВМИДа должна быть разрешена выписка счета",
            "51"   => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом имеет лишь аттестат псевдонима, которого недостаточно для приема средств данным автоматизированным способом",
            "52"   => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом имеет формальный аттестат у которого нет проверенного телефона и проверенной копии паспорта или ИНН и этого недостаточно для приема средств данным автоматизированным способом",
            "54"   => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил дневной лимит на прием средств автоматизированным способом",
            "55"   => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил недельный лимит на прием средств автоматизированным способом",
            "56"   => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил месячный лимит на прием средств автоматизированным способом",
            "61"   => "Превышен лимит долговых обязательств заемщика",
        ];
    }
}