<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml8 extends Interfaces
{
    private $wmid;
    private $purse;

    public function storeData(array $data = [])
    {
        $purse = $data['purse'];
        $wmid = $data['wmid'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($wmid . $purse);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <testwmpurse>
        <wmid>{$wmid}</wmid>
        <purse>{$purse}</purse>
    </testwmpurse>
</w3s.request>
XML;

        $this->wmid = $wmid;
        $this->purse = $purse;
        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        // проверяем существование wmid
        if (
            $this->wmid
            &&
            $this->purse == null
        ) {
            $return = [
                'wmid' => [
                    'exists'            => ($xml->retval == "1") ? true : false,
                    'available'         => (int)$xml->testwmpurse->wmid['available'],
                    'themselfcorrstate' => (int)$xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'          => (int)$xml->testwmpurse->wmid['newattst'],
                ],
            ];
        } // проверяем существование кошелька
        else if (
            $this->wmid == null
            &&
            $this->purse
        ) {
            $exists = ($xml->retval == "1") ? true : false;

            if ($exists) {
                $return['wmid'] = [
                    'wmid'              => (string)$xml->testwmpurse->wmid,
                    'available'         => ($xml->testwmpurse->wmid['available'] == "-1") ? false : true,
                    'themselfcorrstate' => (int)$xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'          => (int)$xml->testwmpurse->wmid['newattst'],
                ];
            }

            $return['purse'] = [
                'exists'                 => $exists,
                'merchant_active_mode'   => (int)$xml->testwmpurse->purse['merchant_active_mode'],
                'merchant_allow_cashier' => (int)$xml->testwmpurse->purse['merchant_allow_cashier'],
            ];
        } // проверяем принадлежность кошелька к wmid
        else {
            $belongs = (((string)$xml->testwmpurse->purse) == $this->purse) ? true : false;

            $return = [
                'wmid'    => [
                    'available'         => ($xml->testwmpurse->wmid['available'] == "-1") ? false : true,
                    'themselfcorrstate' => (int)$xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'          => (int)$xml->testwmpurse->wmid['newattst'],
                ],
                'belongs' => $belongs,
            ];

            if ($belongs) {
                $return['purse'] = [
                    'merchant_active_mode'   => (int)$xml->testwmpurse->purse['merchant_active_mode'],
                    'merchant_allow_cashier' => (int)$xml->testwmpurse->purse['merchant_allow_cashier'],
                ];
            }
        }

        return $return;
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLFindWMPurseNew.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLFindWMPurseCertNew.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе команды. неверный формат команды.",
            "-2"   => "неверный WMId для проверки",
        ];
    }
}