<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml9 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($wmid . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <getpurses>
        <wmid>{$wmid}</wmid>
    </getpurses>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $purses = [];
        foreach ($xml->purses->purse as $purse) {
            $pursename = (string)$purse->pursename;
            $purses[$pursename] = [
                'id'               => (int)$purse['id'],
                'pursename'        => $pursename,
                'amount'           => (float)$purse->amount,
                'desc'             => (string)$purse->desc,
                'outsideopen'      => (int)$purse->outsideopen,
                'outsideopenstate' => (int)$purse->outsideopenstate,
                'lastintr'         => (int)$purse->lastintr,
                'lastouttr'        => (int)$purse->lastouttr,
            ];
        }

        return $purses;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLPurses.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLPursesCert.asp";
    }
}