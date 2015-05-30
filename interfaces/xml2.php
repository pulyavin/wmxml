<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml2 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $at_purse = $data['at_purse'];
        $to_purse = $data['to_purse'];
        $amount = $data['amount'];
        $desc = $data['desc'];
        $protect_period = $data['protect_period'];
        $protect_code = $data['protect_code'];
        $wminvid = $data['wminvid'];
        $onlyauth = $data['onlyauth'];

        $tranid = $this->keeper->getTranid();
        $reqn = $this->getReqn();
        $desc = htmlspecialchars(trim($desc), ENT_QUOTES);
        $protect_code = htmlspecialchars(trim($protect_code), ENT_QUOTES);
        $amount = floatval($amount);

        // ебануться, грёбанный полаллен!
        $desc_temp = mb_convert_encoding($desc, "CP1251", "UTF-8");
        $protect_temp = mb_convert_encoding($protect_code, "CP1251", "UTF-8");

        $sign = $this->keeper->getSign($reqn . $tranid . $at_purse . $to_purse . $amount . $protect_period . $protect_temp . $desc_temp . $wminvid);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <trans>
        <tranid>{$tranid}</tranid>
        <pursesrc>{$at_purse}</pursesrc>
        <pursedest>{$to_purse}</pursedest>
        <amount>{$amount}</amount>
        <period>{$protect_period}</period>
        <pcode>{$protect_code}</pcode>
        <desc>{$desc}</desc>
        <wminvid>{$wminvid}</wminvid>
        <onlyauth>{$onlyauth}</onlyauth>
    </trans>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'id'        => (int)$xml->operation['id'],
            'ts'        => (int)$xml->operation['ts'],
            'tranid'    => (int)$xml->operation->tranid,
            'pursesrc'  => (string)$xml->operation->pursesrc,
            'pursedest' => (string)$xml->operation->pursedest,
            'amount'    => (float)$xml->operation->amount,
            'comiss'    => (float)$xml->operation->comiss,
            'opertype'  => (int)$xml->operation->opertype,
            'period'    => (int)$xml->operation->period,
            'wminvid'   => (int)$xml->operation->wminvid,
            'desc'      => (string)$xml->operation->desc,
            'datecrt'   => new DateTime((string)$xml->operation->datecrt),
            'dateupd'   => new DateTime((string)$xml->operation->dateupd),
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrans.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLTransCert.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе команды. неверный формат команды.",
            "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
            "-1"   => "неверное значение поля w3s.request/reqn",
            "-2"   => "неверное значение поля w3s.request/sign",
            "-3"   => "неверное значение поля w3s.request/trans/tranid",
            "-4"   => "неверное значение поля w3s.request/trans/pursesrc",
            "-5"   => "неверное значение поля w3s.request/trans/pursedest",
            "-6"   => "неверное значение поля w3s.request/trans/amount",
            "-7"   => "неверное значение поля w3s.request/trans/desc",
            "-8"   => "слишком длинное поле w3s.request/trans/pcode",
            "-9"   => "поле w3s.request/trans/pcode не должно быть пустым если w3s.request/trans/period > 0",
            "-10"  => "поле w3s.request/trans/pcode должно быть пустым если w3s.request/trans/period = 0",
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
            "7"    => "кошелек получателя не найден",
            "11"   => "кошелек отправителя не найден",
            "13"   => "сумма транзакции должна быть больше нуля",
            "17"   => "недостаточно денег в кошельке для выполнения операции",
            "21"   => "счет, по которому совершается оплата не найден",
            "22"   => "по указанному счету оплата с протекцией не возможна",
            "25"   => "время действия оплачиваемого счета закончилось",
            "26"   => "в операции должны участвовать разные кошельки",
            "29"   => "типы кошельков отличаются",
            "30"   => "кошелек не поддерживает прямой перевод",
            "35"   => "плательщик не авторизован корреспондентом для выполнения данной операции",
            "58"   => "превышен лимит средств на кошельках получателя",
        ];
    }
}