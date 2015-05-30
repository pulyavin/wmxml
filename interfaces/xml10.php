<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use pulyavin\wmxml\Keeper;
use SimpleXMLElement;

class xml10 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];
        $wminvid = $data['wminvid'];
        $datestart = $data['datestart'];
        $datefinish = $data['datefinis'];

        // устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : (new DateTime("-" . Keeper::MAX_MONTH_DIAPASON . " month"));
        $datefinish = ($datefinish) ? $datefinish : (new DateTime());

        // если крайняя дата вылазиет за 3 месяца - подтягиваем её
        $maxdiapason = new DateTime("-" . Keeper::MAX_MONTH_DIAPASON . " month");
        if ($datestart->getTimestamp() < $maxdiapason->getTimestamp()) {
            $datestart->setTimestamp($maxdiapason->getTimestamp());
        }

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($wmid . $wminvid . $datestart->format(Keeper::DATE_PATTERN) . $datefinish->format(Keeper::DATE_PATTERN) . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <getininvoices>
        <wmid>{$wmid}</wmid>
        <wminvid>{$wminvid}</wminvid>
        <datestart>{$datestart->format(Keeper::DATE_PATTERN)}</datestart>
        <datefinish>{$datefinish->format(Keeper::DATE_PATTERN)}</datefinish>
    </getininvoices>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $invoices = [];
        foreach ($xml->ininvoices->ininvoice as $invoice) {
            $invoices[] = [
                'id'         => (int)$invoice['id'],
                'ts'         => (int)$invoice['ts'],
                'orderid'    => (int)$invoice->orderid,
                'storewmid'  => (string)$invoice->storewmid,
                'storepurse' => (string)$invoice->storepurse,
                'amount'     => (float)$invoice->amount,
                'datecrt'    => new DateTime((string)$invoice->datecrt),
                'dateupd'    => new DateTime((string)$invoice->dateupd),
                'state'      => (int)$invoice->state,
                'address'    => (string)$invoice->address,
                'desc'       => (string)$invoice->desc,
                'period'     => (int)$invoice->period,
                'expiration' => (int)$invoice->expiration,
                'wmtranid'   => (int)$invoice->wmtranid,
            ];
        }

        return $invoices;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLInInvoices.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.webmoney.ru/asp/XMLInInvoicesCert.asp";
    }
}