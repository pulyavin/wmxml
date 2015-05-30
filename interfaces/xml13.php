<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml13 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmtranid = $data['wmtranid'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($wmtranid . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
        <rejectprotect>
            <wmtranid>{$wmtranid}</wmtranid>
        </rejectprotect>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $opertype = (int)$xml->operation->opertype;

        return [
            'id'       => (int)$xml->operation['id'],
            'ts'       => (int)$xml->operation['ts'],
            'opertype' => (int)$xml->operation->opertype,
            'dateupd'  => new DateTime((string)$xml->operation->dateupd),
            'success'  => (!$opertype) ? true : false,
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLRejectProtect.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLRejectProtectCert.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе команды. неверный формат команды.",
            "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
            "-1"   => "неверное значение поля w3s.request/reqn",
            "-2"   => "неверное значение поля w3s.request/sign",
            "-11"  => "неверное значение поля w3s.request/trans/wminvid",
            "-12"  => "идентификатор переданный в поле w3s.request/wmid не зарегистрирован",
            "-14"  => "проверка подписи не прошла",
            "-15"  => "неверное значение поля w3s.request/wmid",
            "102"  => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
            "103"  => "транзакция с таким значением поля w3s.request/trans/tranid уже выполнялась",
            "110"  => "нет доступа к интерфейсу",
            "111"  => "попытка перевода с кошелька не принадлежащего WMID, которым подписывается запрос; при этом доверие не установлено.",
            "5"    => "идентификатор отправителя не найден",
            "6"    => "корреспондент не найден",
            "17"   => "недостаточно денег в кошельке для выполнения операции",
            "21"   => "счет, по которому совершается оплата не найден",
            "22"   => "по указанному счету оплата с протекцией не возможна",
            "25"   => "время действия оплачиваемого счета закончилось",
            "30"   => "кошелек не поддерживает прямой перевод",
            "35"   => "плательщик не авторизован корреспондентом для выполнения данной операции",
            "58"   => "превышен лимит средств на кошельках получателя",
        ];
    }
}