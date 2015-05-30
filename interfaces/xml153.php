<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml153 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $purse = $data['purse'];
        $is_inv = $data['is_inv'];
        $is_trans = $data['is_trans'];
        $is_purse = $data['is_purse'];
        $is_transhist = $data['is_transhist'];
        $masterwmid = $data['masterwmid'];
        $limit = $data['limit'];
        $daylimit = $data['daylimit'];
        $weeklimit = $data['weeklimit'];
        $monthlimit = $data['monthlimit'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($this->keeper->wmid . $purse . $masterwmid . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <trust inv="{$is_inv}" trans="{$is_trans}" purse="{$is_purse}" transhist="{$is_transhist}">
        <masterwmid>{$masterwmid}</masterwmid>
        <slavewmid>{$this->keeper->wmid}</slavewmid>
        <purse>{$purse}</purse>
        <limit>{$limit}</limit>
        <daylimit>{$daylimit}</daylimit>
        <weeklimit>{$weeklimit}</weeklimit>
        <monthlimit>{$monthlimit}</monthlimit>
    </trust>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'id'           => (int)$xml->trust['id'],
            'is_inv'       => ((int)$xml->trust['inv']) ? true : false,
            'is_trans'     => ((int)$xml->trust['trans']) ? true : false,
            'is_purse'     => ((int)$xml->trust['purse']) ? true : false,
            'is_transhist' => ((int)$xml->trust['transhist']) ? true : false,
            'purse'        => (string)$xml->trust->purse,
            'master'       => (string)$xml->trust->master,
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustSave2.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustSave2Cert.asp";
    }
}