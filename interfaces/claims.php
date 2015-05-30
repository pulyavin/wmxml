<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class claims extends Interfaces
{
    private $url = "http://arbitrage.webmoney.ru/asp/XMLGetWMIDClaims.asp";

    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];

        $xml = <<<XML
<request>
    <wmid>{$wmid}</wmid>
</request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return $xml;
    }

    public function getUrlClassic()
    {
        return $this->url;
    }

    public function getUrlLight()
    {
        return $this->url;
    }
}