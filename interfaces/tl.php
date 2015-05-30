<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class tl extends Interfaces
{
    private $url = "https://debt.wmtransfer.com/xmlTrustLevelsGet.aspx";

    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];

        $xml = <<<XML
<trustlimits>
    <getlevels>
        <signerwmid>{$this->keeper->wmid}</signerwmid>
        <wmid>{$wmid}</wmid>
    </getlevels>
</trustlimits>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'value' => (int)$xml->tl['val'],
        ];
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