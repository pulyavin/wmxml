<?php namespace pulyavin\wmxml;

abstract class Interfaces implements Contract {
    protected $xml;
    protected $keeper;

    public function __construct(Keeper $keeper) {
        $this->keeper = $keeper;
    }

    public function getXml() {
        return $this->xml;
    }

    public function getUrlClassic() {
        throw new \Exception("Undefined API URL of Classic!");
    }

    public function getUrlLight() {
        throw new \Exception("Undefined API URL of Light!");
    }

    public function getName() {
        return end(explode("\\", get_called_class()));
    }

    /**
     * возвращает уникальный, увеличивающийся REQN
     * @return integer
     */
    protected function getReqn()
    {
        $time = microtime();
        return substr($time, 11) . substr($time, 2, 5);
    }

    public function getErrors()
    {
        return [];
    }

    public function getError($code)
    {
        $errors = $this->getErrors();

        return isset($errors[$code]) ? $errors[$code] : "Undefined error #{$code}";
    }
}