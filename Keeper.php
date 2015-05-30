<?php namespace pulyavin\wmxml;

use baibaratsky\WebMoney\Signer;
use pulyavin\streams\Stream;

class Keeper
{
    // максимальный диапазон выборки в месяцах
    const MAX_MONTH_DIAPASON = 3;

    const TYPE_CLASSIC = "classic";
    const TYPE_LIGHT = "light";
    const DATE_PATTERN = "Ymd H:i:s";

    // type of WebMoney Keeper: WinPro (Classic) or WebPro (Light)
    public $keeper;

    public $wmid;

    private $wmsigner;
    private $key;
    private $tranid;
    private $rootca;
    private $connect;
    private $timeout;

    public function __construct($keeper_type, array $data)
    {
        $this->wmid = isset($data['wmid']) ? $data['wmid'] : null;
        $wmsigner = isset($data['wmsigner']) ? $data['wmsigner'] : null;
        $rootca = isset($data['rootca']) ? $data['rootca'] : null;
        $tranid = isset($data['tranid']) ? $data['tranid'] : null;
        $key = isset($data['key']) ? $data['key'] : null;

        $this->connect = isset($data['connect']) ? $data['connect'] : 5;
        $this->timeout = isset($data['timeout']) ? $data['timeout'] : 5;

        if (empty($this->wmid)) {
            throw new \Exception("Unknown WMID");
        }

        if (empty($rootca)) {
            throw new \Exception("Unknown rootca-file path");
        }

        if (!realpath($rootca)) {
            throw new \Exception("Incorrect rootca-file path");
        }


        // WebMoney Keeper: WinPro (Classic)
        if ($keeper_type == self::TYPE_CLASSIC) {
            // it is C-Signer
            if (!self::isPhpSigner($wmsigner)) {
                if (empty($wmsigner)) {
                    throw new \Exception("Unknown wmsigner-file path");
                }
                if (!realpath($wmsigner)) {
                    throw new \Exception("Incorrect wmsigner-file path");
                }

                $wmsigner = realpath($wmsigner);
            }

            $this->wmsigner = $wmsigner;
        } // WebMoney Keeper: WebPro (Light)
        else if ($keeper_type == self::TYPE_LIGHT) {
            if (empty($key)) {
                throw new \Exception("Unknown key-file path");
            }

            if (!realpath($key)) {
                throw new \Exception("Incorrect key-file path");
            }

            $this->key = realpath($key);
        } // Undefined Keeper type
        else {
            throw new \Exception("Incorrect type of WebMoney Keeper");
        }

        $this->keeper = $keeper_type;
        $this->rootca = realpath($rootca);

        // found path to tranid.txt
        if (!empty($tranid)) {
            if (!realpath($tranid)) {
                throw new \Exception("Incorrect tranid-file path");
            }

            if (!is_writable(realpath($tranid))) {
                throw new \Exception("tranid file not writable");
            }

            $this->tranid = realpath($tranid);
        }
    }

    /**
     * Обращается к API, получает XML, распарсивает его и возвращает
     *
     * @param Contract $interface
     * @return \SimpleXMLElement
     * @throws \Exception
     */
    public function sendRequest(Contract $interface)
    {
        try {
            if ($this->keeper == Keeper::TYPE_CLASSIC) {
                $stream = new Stream($interface->getUrlClassic());
            } else {
                $stream = new Stream($interface->getUrlLight());
                $stream->setSslKey($this->key);
            }

            $stream->setPost($interface->getXml());
            $stream->setCA($this->rootca);
            $stream->setTimeout($this->connect, $this->timeout);

            $response = $stream->exec();
        } catch (\Exception $e) {
            throw new \Exception("Connection error: " . $e->getMessage());
        }

        if (empty($response)) {
            throw new \Exception("No incoming xml-data");
        }

        $xml = simplexml_load_string($response);

        // parse return value status
        $code = isset($xml['retval']) ? $xml['retval'] : $xml->retval;
        $code = (int)$code;

        // huck for XML8, it's all right
        if ($interface->getName() == "8" && $code == "1") {
            $code = "0";
        }

        // have a problem
        if ($code) {
            $error = $interface->getError($code);

            // try to find description in xml-packet
            if ($error === false && isset($xml->retdesc)) {
                $error = $xml->retdesc;
            }

            throw new \Exception($error, $code);
        }

        return $xml;
    }

    /**
     * Возвращает и инкрементирует транзакционный идентификатор платежа из файла tranid.txt
     *
     * @return int
     * @throws \Exception
     */
    public function getTranid()
    {
        // а вдруг нам не указали путь до файла tranid.txt
        if (empty($this->tranid)) {
            throw new \Exception("Have not path tranid-file!");
        }

        $file = fopen($this->tranid, "r+");

        $start = time(); // время старта попытки блокировки
        // пытаемся получить блокировку в течении 3-х секунд
        $locked = false;
        while ((time(true) - $start) < 3) {
            $locked = flock($file, LOCK_EX);
            // не удалось заблокировать файл
            if (!$locked) {
                // ну чтож, ждём 100мс
                usleep(100000);
            }
        }

        // не уалось получить блокировку
        if (!$locked) {
            throw new \Exception("Can't get lock tranid file");
        }

        $tranid = (int)fgets($file);
        $tranid++;
        rewind($file);
        fwrite($file, $tranid);
        fflush($file);
        flock($file, LOCK_UN);
        fclose($file);

        return $tranid;
    }

    /**
     * Обёртка над C и PHP подписчиками
     *
     * @param $string
     * @return string
     */
    public function getSign($string)
    {
        // это PHP-подписчик
        if (self::isPhpSigner($this->wmsigner)) {
            return $this->phpSigner($string);
        } // а это C-подписчик
        else {
            return $this->cSigner($string);
        }
    }

    /**
     * PHP-подписчик
     *
     * @param $string
     * @return string
     */
    private function phpSigner($string)
    {
        return $this->wmsigner->sign($string);
    }

    /**
     * C-подписчик: пишет в бинарный wmsigner и получает подписанную строку
     *
     * @param  string $string строка для подписи
     * @return string
     */
    private function cSigner($string)
    {
        // если это WebMoney Keeper WebPro, то подписывать ему ничего не надо
        if ($this->keeper == "light") {
            return;
        }

        // мы должны быть в папке, т.к. там файл ключей и ini-файл
        chdir(dirname($this->wmsigner));

        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "r"]
        ];

        $process = proc_open($this->wmsigner, $descriptorspec, $pipes);
        fwrite($pipes[0], "$string\004\r\n");
        fclose($pipes[0]);

        $string = fgets($pipes[1], 133);
        fclose($pipes[1]);
        proc_close($process);

        return $string;
    }

    /**
     * @param $object
     * @return bool
     */
    private static function isPhpSigner($object)
    {
        return $object instanceof Signer;
    }
}