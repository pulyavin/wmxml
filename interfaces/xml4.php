<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use pulyavin\wmxml\Constants;
use pulyavin\wmxml\Keeper;
use SimpleXMLElement;

class xml4 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $purse = $data['purse'];
        $datestart = $data['datestart'];
        $datefinish = $data['datefinish'];
        $wminvid = $data['wminvid'];
        $orderid = $data['orderid'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($purse . $reqn);

        // устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : (new DateTime("-" . Constants::MAX_MONTH_DIAPASON . " month"));
        $datefinish = ($datefinish) ? $datefinish : (new DateTime());

        // если крайняя дата вылазиет за 3 месяца - подтягиваем её
        $maxdiapason = new DateTime("-" . Constants::MAX_MONTH_DIAPASON . " month");
        if ($datestart->getTimestamp() < $maxdiapason->getTimestamp()) {
            $datestart->setTimestamp($maxdiapason->getTimestamp());
        }

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <getoutinvoices>
        <purse>{$purse}</purse>
        <wminvid>{$wminvid}</wminvid>
        <orderid>{$orderid}</orderid>
        <datestart>{$datestart->format(Keeper::DATE_PATTERN)}</datestart>
        <datefinish>{$datefinish->format(Keeper::DATE_PATTERN)}</datefinish>
    </getoutinvoices>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $invoices = [];
        foreach ($xml->outinvoices->outinvoice as $invoice) {
            $invoices[] = [
                'id'            => (int)$invoice['id'],
                'ts'            => (int)$invoice['ts'],
                'orderid'       => (int)$invoice->orderid,
                'storepurse'    => (string)$invoice->storepurse,
                'customerwmid'  => (string)$invoice->customerwmid,
                'customerpurse' => (string)$invoice->customerpurse,
                'amount'        => (float)$invoice->amount,
                'datecrt'       => new DateTime((string)$invoice->datecrt),
                'dateupd'       => new DateTime((string)$invoice->dateupd),
                'state'         => (int)$invoice->state,
                'address'       => (string)$invoice->address,
                'desc'          => (string)$invoice->desc,
                'period'        => (int)$invoice->period,
                'expiration'    => (int)$invoice->expiration,
                'wmtranid'      => (int)$invoice->wmtranid,
            ];
        }

        return $invoices;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLOutInvoices.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.webmoney.ru/asp/XMLOutInvoices.asp";
    }

    public function getErrors()
    {
        return [
            "111" => "попытка запроса информации по кошельку не принадлежащему WMID, которым подписывается запрос; при этом доверие не установлено.",
        ];
    }
}