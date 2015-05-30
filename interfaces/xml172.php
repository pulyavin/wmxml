<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml172 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $contractid = $data['contractid'];
        $mode = $data['mode'];

        $sign = $this->getSign($contractid . $mode);

        $xml = <<<XML
<contract.request>
    <wmid>{$this->keeper->wmid}</wmid>
    <contractid>{$contractid}</contractid>
    <mode>{$mode}</mode>
    <sign>{$sign}</sign>
</contract.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $contractinfo = [];
        foreach ($xml->contractinfo->row as $contract) {
            $contractinfo[] = [
                'contractid'     => (int)$contract['contractid'],
                'wmid'           => (string)$contract['wmid'],
                'acceptdate'     => (string)$contract['acceptdate'],
                'signature'      => (string)$contract['signature'],
                'smsacceptcode'  => (int)$contract['smsacceptcode'],
                'smsacceptphone' => (string)$contract['smsacceptphone'],
                'smsacceptdate'  => (string)$contract['smsacceptdate'],
            ];
        }

        return $contractinfo;
    }

    public function getUrlClassic()
    {
        return "https://arbitrage.webmoney.ru/xml/X17_GetContractInfo.aspx";
    }
}