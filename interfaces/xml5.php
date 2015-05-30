<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml5 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmtranid = $data['wmtranid'];
        $pcode = $data['pcode'];

        $reqn = $this->getReqn();
        $sign = $this->getSign($wmtranid . $pcode . $reqn);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <finishprotect>
        <wmtranid>{$wmtranid}</wmtranid>
        <pcode>{$pcode}</pcode>
    </finishprotect>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $opertype = (int)$xml->operation->opertype;

        return [
            'id'       => (int)$xml->operation['id'],
            'ts'       => (int)$xml->operation['ts'],
            'opertype' => $opertype,
            'dateupd'  => new DateTime((string)$xml->operation->dateupd),
            'success'  => (!$opertype) ? true : false,
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLFinishProtect.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLFinishProtectCert.asp";
    }

    public function getErrors()
    {
        return [
            "20" => "20 - код протекции неверен, но кол-во попыток ввода кода (8) не исчерпано",
        ];
    }
}