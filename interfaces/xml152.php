<?php namespace pulyavin\wmxml\interfaces;

class xml152 extends xml151
{
    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustList2.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.webmoney.ru/asp/XMLTrustList2Cert.asp";
    }
}