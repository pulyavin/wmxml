<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml171 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $name = $data['name'];
        $ctype = $data['ctype'];
        $text = $data['text'];
        $accesslist = $data['accesslist'];

        $name = htmlspecialchars(trim($name), ENT_QUOTES);
        $text = htmlspecialchars(trim($text), ENT_QUOTES);

        // ебануться, грёбанный полаллен!
        $name_temp = mb_convert_encoding($name, "CP1251", "UTF-8");

        $sign = $this->keeper->getSign($this->keeper->wmid . mb_strlen($name_temp) . $ctype);

        $xml = <<<XML
<contract.request>
    <sign_wmid>{$this->keeper->wmid}</sign_wmid>
    <name>{$name}</name>
    <ctype>{$ctype}</ctype>
    <text><![CDATA[{$text}]]></text>
    <sign>{$sign}</sign>
    <accesslist>
XML;

        foreach ($accesslist as $wmid) {
            $xml .= '<wmid>' . $wmid . '</wmid>';
        }

        $xml .= '
    </accesslist>
</contract.request>
';

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'contractid' => (int)$xml->contractid,
        ];
    }

    public function getUrlClassic()
    {
        return "https://arbitrage.webmoney.ru/xml/X17_CreateContract.aspx";
    }
}