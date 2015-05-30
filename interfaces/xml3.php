<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use pulyavin\wmxml\Constants;
use pulyavin\wmxml\Keeper;
use SimpleXMLElement;

class xml3 extends Interfaces
{
    private $purse;

    public function storeData(array $data = [])
    {
        $purse = $data['purse'];
        $datestart = $data['datestart'];
        $datefinish = $data['datefinish'];
        $wmtranid = $data['wmtranid'];
        $tranid = $data['tranid'];
        $wminvid = $data['wminvid'];
        $orderid = $data['orderid'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($purse . $reqn);

        // устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : (new DateTime("-" . Keeper::MAX_MONTH_DIAPASON . " month"));
        $datefinish = ($datefinish) ? $datefinish : (new DateTime());

        // если крайняя дата вылазиет за 3 месяца - подтягиваем её
        $maxdiapason = new DateTime("-" . Keeper::MAX_MONTH_DIAPASON . " month");
        if ($datestart->getTimestamp() < $maxdiapason->getTimestamp()) {
            $datestart->setTimestamp($maxdiapason->getTimestamp());
        }

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <getoperations>
        <purse>{$purse}</purse>
        <wmtranid>{$wmtranid}</wmtranid>
        <tranid>{$tranid}</tranid>
        <wminvid>{$wminvid}</wminvid>
        <orderid>{$orderid}</orderid>
        <datestart>{$datestart->format(Keeper::DATE_PATTERN)}</datestart>
        <datefinish>{$datefinish->format(Keeper::DATE_PATTERN)}</datefinish>
    </getoperations>
</w3s.request>
XML;

        $this->purse = $purse;
        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $operations = [];
        foreach ($xml->operations->operation as $operation) {
            if ($operation->pursesrc == $this->purse) {
                $type = Constants::TRANSAC_OUT;
                $corrpurse = (string)$operation->pursedest;
            } else {
                $type = Constants::TRANSAC_IN;
                $corrpurse = (string)$operation->pursesrc;
            }

            $operations[] = [
                'id'        => (int)$operation['id'],
                'ts'        => (int)$operation['ts'],
                'pursesrc'  => (string)$operation->pursesrc,
                'pursedest' => (string)$operation->pursedest,
                'type'      => $type,
                'corrpurse' => $corrpurse,
                'amount'    => (float)$operation->amount,
                'comiss'    => (float)$operation->comiss,
                'opertype'  => (int)$operation->opertype,
                'wminvid'   => (int)$operation->wminvid,
                'orderid'   => (int)$operation->orderid,
                'tranid'    => (int)$operation->tranid,
                'period'    => (int)$operation->period,
                'desc'      => (string)$operation->desc,
                'datecrt'   => new DateTime((string)$operation->datecrt),
                'dateupd'   => new DateTime((string)$operation->dateupd),
                'corrwm'    => (string)$operation->corrwm,
                'rest'      => (float)$operation->rest,
                'timelock'  => (string)$operation->timelock,
            ];
        }

        return $operations;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLOperations.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLOperationsCert.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе команды. неверный формат команды.",
            "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
            "-1"   => "неверное значение поля w3s.request/wmid",
            "-2"   => "неверное значение поля w3s.request/getoperations/purse",
            "-3"   => "неверное значение поля w3s.request/sign",
            "-4"   => "неверное значение поля w3s.request/reqn",
            "-5"   => "проверка подписи не прошла",
            "-7"   => "неверное значение поля w3s.request/getoperations/datestart",
            "-8"   => "неверное значение поля w3s.request/getoperations/datefinish",
            "-9"   => "WMID указанный в поле w3s.request/wmid не найден",
            "102"  => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
            "111"  => "попытка запроса истории по кошельку не принадлежащему WMID, которым подписывается запрос; при этом доверие не установлено.",
            "1004" => "слишком большой диапазон выборки",
        ];
    }
}