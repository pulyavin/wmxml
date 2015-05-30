<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml151 extends Interfaces
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
    <gettrustlist>
        <wmid>{$wmid}</wmid>
    </gettrustlist>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        $trustlist = [];
        foreach ($xml->trustlist->trust as $trust) {
            $trustlist[] = [
                'id'           => (int)$trust['id'],
                'is_inv'       => ((int)$trust['inv']) ? true : false,
                'is_trans'     => ((int)$trust['trans']) ? true : false,
                'is_purse'     => ((int)$trust['purse']) ? true : false,
                'is_transhist' => ((int)$trust['transhist']) ? true : false,
                'master'       => (string)$trust->master,
                'purse'        => (string)$trust->purse,
                'daylimit'     => (float)$trust->daylimit,
                'dlimit'       => (float)$trust->dlimit,
                'wlimit'       => (float)$trust->wlimit,
                'mlimit'       => (float)$trust->mlimit,
                'dsum'         => (float)$trust->dsum,
                'wsum'         => (float)$trust->wsum,
                'msum'         => (float)$trust->msum,
                'lastsumdate'  => (string)$trust->lastsumdate,
                'storeswmid'   => (string)$trust->storeswmid,
            ];
        }

        return $trustlist;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustList.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustListCert.asp";
    }
}