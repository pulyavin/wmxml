<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class bl extends Interfaces
{
    private $url = "https://stats.wmtransfer.com/levels/XMLWMIDLevel.aspx";

    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];

        $xml = <<<XML
<WMIDLevel.request>
    <signerwmid>{$this->keeper->wmid}</signerwmid>
    <wmid>{$wmid}</wmid>
</WMIDLevel.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'value' => (int)$xml->level,
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