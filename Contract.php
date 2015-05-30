<?php namespace pulyavin\wmxml;

use SimpleXMLElement;

interface Contract {
    /**
     * Generate and stored XML-packet by data
     *
     * @param array $data
     * @return mixed
     */
    public function storeData(array $data = []);

    /**
     * Return stored XML-packed
     *
     * @return mixed
     */
    public function getXml();

    /**
     * Return parsed data by XML
     *
     * @param SimpleXMLElement $response
     * @return mixed
     */
    public function getData(SimpleXMLElement $response);

    /**
     * Returns text error by code
     *
     * @param $code
     * @return mixed
     */
    public function getError($code);

    /**
     * Return array of possible errors
     *
     * @return mixed
     */
    public function getErrors();

    /**
     * Returns URL of this interface for WinPro (Classic)
     *
     * @return mixed
     */
    public function getUrlClassic();

    /**
     * Returns URL of this interface for WebPro (Light)
     *
     * @return mixed
     */
    public function getUrlLight();

    /**
     * Returns name of current interface
     *
     * @return mixed
     */
    public function getName();
}