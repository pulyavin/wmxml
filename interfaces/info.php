<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class info extends Interfaces
{
    private $url = "https://passport.webmoney.ru/xml/XMLGetWMIDInfo.aspx";

    public function storeData(array $data = [])
    {
        $search = $data['search'];
        $is_purse = $data['is_purse'];

        $xml = '<request>';
        // мы проверяем кошелёк
        if ($is_purse) {
            $xml .= '<purse>' . $search . '</purse>';
        } // мы проверяем wmid
        else {
            $xml .= '<wmid>' . $search . '</wmid>';
        }
        $xml .= '</request>';

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        // собираем список wmid's
        $wmids = [];
        foreach ($xml->certinfo->wmids->row as $data) {
            $wmids[] = [
                'wmid'    => (string)$data['wmid'],
                'level'   => (int)$data['level'],
                'datereg' => (string)$data['datereg'],
            ];
        }

        // собираем список userinfo's
        $userinfo = [];
        foreach ($xml->certinfo->userinfo->value->row as $data) {
            $userinfo[] = [
                'fname' => (string)$data['fname'],
                'iname' => (string)$data['iname'],
                'oname' => (string)$data['oname'],
                'name'  => (string)$data['name'],
            ];
        }

        // собираем список claims's
        $claims = [];
        foreach ($xml->certinfo->claims->row as $data) {
            $claims[] = [
                'posclaimscount' => (int)$data['posclaimscount'],
                'negclaimscount' => (int)$data['negclaimscount'],
                'claimslastdate' => (string)$data['claimslastdate'],
            ];
        }

        // собираем список urls's
        $urls = [];
        foreach ($xml->certinfo->urls->row as $data) {
            $urls[] = [
                'attestaturl'          => (string)$data['attestaturl'],
                'attestaticonurl'      => (string)$data['attestaticonurl'],
                'attestatsmalliconurl' => (string)$data['attestatsmalliconurl'],
                'claimsurl'            => (string)$data['claimsurl'],
            ];
        }

        return [
            'tid'      => (int)$xml->certinfo->attestat->row['tid'],
            'typename' => (string)$xml->certinfo->attestat->row['typename'],
            'wmids'    => $wmids,
            'userinfo' => $userinfo,
            'claims'   => $claims,
            'urls'     => $urls,
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

    public function getErrors()
    {
        return [
            "404" => "Данный идентификатор в системе не зарегистрирован",
        ];
    }
}