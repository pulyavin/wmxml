<?php namespace pulyavin\wmxml;

use DateTime;

use pulyavin\wmxml\interfaces\xml1;
use pulyavin\wmxml\interfaces\xml2;
use pulyavin\wmxml\interfaces\xml3;
use pulyavin\wmxml\interfaces\xml4;
use pulyavin\wmxml\interfaces\xml5;
use pulyavin\wmxml\interfaces\xml6;
use pulyavin\wmxml\interfaces\xml8;
use pulyavin\wmxml\interfaces\xml9;
use pulyavin\wmxml\interfaces\xml10;
use pulyavin\wmxml\interfaces\xml11;
use pulyavin\wmxml\interfaces\xml13;
use pulyavin\wmxml\interfaces\xml14;
use pulyavin\wmxml\interfaces\xml151;
use pulyavin\wmxml\interfaces\xml152;
use pulyavin\wmxml\interfaces\xml153;
use pulyavin\wmxml\interfaces\xml171;
use pulyavin\wmxml\interfaces\xml172;
use pulyavin\wmxml\interfaces\xml18;

use pulyavin\wmxml\interfaces\bl;
use pulyavin\wmxml\interfaces\tl;
use pulyavin\wmxml\interfaces\claims;
use pulyavin\wmxml\interfaces\info;

class WMXml
{
    private $keeper;

    /**
     * иницализация объекта
     * @param string $keeper_type тип WebMoney Keeper: WinPro (Classic) или WebPro (Light)
     * @param array $data конфигурационный массив, содержащий следующие параметры:
     * [
     *   "wmid", // WMID, подписывающего запросы
     *   "tranid" // путь до txt-файла, хранящего значение следущего номера транзакции (tranid), если будет использоваться XML2
     *   "rootca", // путь до корневого сертификата WebMoney Transfer
     *
     *   // если используется WM Keeper WinPro (Classic)
     *   "wmsigner"
     *      // способ №1 - использовать скомпилированный wmsigner
     *      "wmsigner", // путь до бинарного файла подписчика wmsigner
     *      // способ №2 - использовать wmsigner на PHP
     *      "wmsigner", // инстанс класса Signer
     *
     *   // если используется WM Keeper WebPro (Light)
     *   "key", // путь до файла ключа
     *
     *   // необязательные атрибуты
     *   "connect", // время таймаута открытия соеднения в библиотеке CURL
     *   "timeout" // время таймаута ожидания получения ответа в библиотеке CURL
     * ]
     * @throws \Exception
     */
    public function __construct($keeper_type, array $data)
    {
        $this->keeper = new Keeper($keeper_type, $data);
    }

    /**
     * XML: X1, выписка счёта
     * @param  string $wmid WMID покупателя
     * @param  string $purse номер кошелька магазина, на который необходимо оплатить счет
     * @param  double $amount сумма счета
     * @param  string $desc описание товара или услуги
     * @param  integer $orderid номер счета в системе учета магазина; любое целое число без знака
     * @param  string $address адрес доставки товара
     * @param  integer $period максимально допустимый срок протекции сделки в днях
     * @param  integer $expiration максимально допустимый срок оплаты счета в днях
     * @param  integer $onlyauth учитывать разрешение получателя
     * @param  integer $shop_id номер магазина в каталоге Мегасток
     * @return array
     */
    public function xml1($wmid, $purse, $amount, $desc, $orderid = 0, $address = "", $period = 0, $expiration = 7, $onlyauth = 0, $shop_id = 0)
    {
        $data = [
            'wmid'       => $wmid,
            'purse'      => $purse,
            'amount'     => $amount,
            'desc'       => $desc,
            'orderid'    => $orderid,
            'address'    => $address,
            'period'     => $period,
            'expiration' => $expiration,
            'onlyauth'   => $onlyauth,
            'shop_id'    => $shop_id
        ];

        $interface = new xml1($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X2, отправка перевода
     * @param  string $at_purse номер кошелька с которого выполняется перевод (отправитель)
     * @param  string $to_purse номер кошелька, на который выполняется перевод (получатель)
     * @param  double $amount переводимая сумма
     * @param  string $desc описание оплачиваемого товара или услуги
     * @param  integer $protect_period срок протекции сделки в днях
     * @param  string $protect_code код протекции сделки
     * @param  integer $wminvid номер счета (в системе WebMoney), по которому выполняется перевод; целое число > 0; если 0 - перевод не по счету
     * @param  integer $onlyauth учитывать разрешение получателя
     * @return array
     */
    public function xml2($at_purse, $to_purse, $amount, $desc, $protect_period = 0, $protect_code = "", $wminvid = 0, $onlyauth = 0)
    {
        $data = [
            'at_purse'       => $at_purse,
            'to_purse'       => $to_purse,
            'amount'         => $amount,
            'desc'           => $desc,
            'protect_period' => $protect_period,
            'protect_code'   => $protect_code,
            'wminvid'        => $wminvid,
            'onlyauth'       => $onlyauth
        ];

        $interface = new xml2($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X3, получение истории операций
     * @param string $purse номер кошелька для которого запрашивается операция
     * @param DateTime $datestart минимальное время и дата выполнения операции (ГГГГММДД ЧЧ:ММ:СС)
     * @param DateTime $datefinish максимальное время и дата выполнения операции (ГГГГММДД ЧЧ:ММ:СС)
     * @param integer $wmtranid номер операции (в системе WebMoney)
     * @param integer $tranid номер перевода в системе учета отправителя
     * @param integer $wminvid номер счета (в системе WebMoney) по которому выполнялась операция
     * @param integer $orderid номер счета в системе учета магазина
     * @return array
     * @throws Exception
     */
    public function xml3($purse, DateTime $datestart = null, DateTime $datefinish = null, $wmtranid = 0, $tranid = 0, $wminvid = 0, $orderid = 0)
    {
        $data = [
            'purse'      => $purse,
            'datestart'  => $datestart,
            'datefinish' => $datefinish,
            'wmtranid'   => $wmtranid,
            'tranid'     => $tranid,
            'wminvid'    => $wminvid,
            'orderid'    => $orderid
        ];

        $interface = new xml3($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X4, получение истории выписанных счетов по кошельку. Проверка оплаты счета
     * @param string $purse номер кошелька для оплаты на который выписывался счет
     * @param DateTime $datestart минимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @param DateTime $datefinish максимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @param integer $wminvid номер счета (в системе WebMoney)
     * @param integer $orderid номер счета в системе учета магазина
     * @return array
     * @throws Exception
     */
    public function xml4($purse, DateTime $datestart = null, DateTime $datefinish = null, $wminvid = 0, $orderid = 0)
    {
        $data = [
            'purse'      => $purse,
            'datestart'  => $datestart,
            'datefinish' => $datefinish,
            'wminvid'    => $wminvid,
            'orderid'    => $orderid
        ];

        $interface = new xml4($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X5, ввод кода протекции для завершения операции с протекцией сделки
     * @param  integer $wmtranid уникальный номер платежа в системе учета WebMoney
     * @param  string $pcode код протекции сделки
     * @return array
     */
    public function xml5($wmtranid, $pcode)
    {
        $data = [
            'wmtranid' => $wmtranid,
            'pcode'    => $pcode,
        ];

        $interface = new xml5($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X6, отправка сообщений
     * @param  string $wmid WM-идентификатор получателя сообщения
     * @param  string $message текст сообщения
     * @param  string $subject текст сообщения
     * @return array
     */
    public function xml6($wmid, $message, $subject = "")
    {
        $data = [
            'wmid'    => $wmid,
            'message' => $message,
            'subject' => $subject,
        ];

        $interface = new xml6($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X8, проверка существований и принадлежности wmid и кошелька
     * @param  string $wmid проверяемый на существование wmid
     * @param  string $purse проверяемый на существование кошелёк
     * @return array
     */
    public function xml8($wmid = null, $purse = null)
    {
        $data = [
            'wmid'  => $wmid,
            'purse' => $purse,
        ];

        $interface = new xml8($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X9, получение балансов по кошелькам
     * @param  string $wmid доверенный wmid
     * @return array
     */
    public function xml9($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid
        ];

        $interface = new xml9($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X10, получение списка счетов на оплату
     * @param string $wmid WM-идентификатор, которому был выписан счет (счета) на оплату
     * @param integer $wminvid номер счета (в системе WebMoney)
     * @param DateTime $datestart
     * @param DateTime $datefinish
     * @return array
     * @throws Exception
     */
    public function xml10($wmid = null, $wminvid = 0, DateTime $datestart = null, DateTime $datefinish = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid'      => $wmid,
            'wminvid'   => $wminvid,
            'datestart' => $datestart,
            'datefinis' => $datefinish
        ];

        $interface = new xml10($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X11, получение информации из аттестата владельца по WM-идентификатору
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function xml11($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid,
        ];

        $interface = new xml11($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X13, возврат незавершенного платежа с протекцией
     * @param  integer $wmtranid номер транзакции (целое положительное число) по внутреннему учету WebMoney Transfer (wmtranid)
     * при этом тип этой транзакции должен быть с протекцией (по коду или по времени), а состояние транзакции с протекцией - не завершена
     * @return array
     */
    public function xml13($wmtranid)
    {
        $data = [
            'wmtranid' => $wmtranid
        ];

        $interface = new xml13($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X14, бескомиссионный возврат средств отправителю (покупателю)
     * @param  integer $wmtranid номер транзакции (целое положительное число) по внутреннему учету WebMoney Transfer (wmtranid), которую необходимо вернуть, при этом тип этой транзакции должен быть - обычная (opertype=0)
     * @param  double $amount сумма, которую необходимо вернуть, она не может превышать исходную сумму входящей транзакции
     * @param  integer $moneybackphone телефон покупателя
     * @param  string $capitallerpursesrc кошелек капиталлера
     * @return array
     */
    public function xml14($wmtranid, $amount, $moneybackphone = null, $capitallerpursesrc = null)
    {
        $data = [
            'wmtranid'           => $wmtranid,
            'amount'             => $amount,
            'moneybackphone'     => $moneybackphone,
            'capitallerpursesrc' => $capitallerpursesrc
        ];

        $interface = new xml14($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X15 [1], просмотр и изменение текущих настроек управления "по доверию"
     * [1]: получение списка кошельков, управление которыми доверяет, идентификатор, совершающий запрос;
     * [2]: получение списка идентификаторов и их кошельков, которые доверяют, идентификатору, совершающему запрос;
     * [3]: создание или изменение настроек доверия для определённого кошелька или идентификатора;
     * @param  string $wmid
     * @return array
     */
    public function xml151($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid,
        ];

        $interface = new xml151($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }


    /**
     * XML: X15 [1], просмотр и изменение текущих настроек управления "по доверию"
     * [11]:
     * [12]: получение списка идентификаторов и их кошельков, которые доверяют, идентификатору, совершающему запрос;
     * [2]: создание или изменение настроек доверия для определённого кошелька или идентификатора;
     * @param  string $wmid если необходимо просмотреть свои настройки, то указывать этот параметр не надо
     * @return array
     */
    public function xml152($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid,
        ];

        $interface = new xml152($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X15 [3], просмотр и изменение текущих настроек управления "по доверию"
     * [1]: получение списка кошельков, управление которыми доверяет, идентификатор, совершающий запрос;
     * [2]: получение списка идентификаторов и их кошельков, которые доверяют, идентификатору, совершающему запрос;
     * [3]: создание или изменение настроек доверия для определённого кошелька или идентификатора;
     * @param string $purse наш кошелёк, на который устанавливается доверие
     * @param bool|int $is_inv разрешить(1) или нет(0) идентификатору в теге masterwmid выпиcывать счета на доверяемый кошелек purse
     * @param bool|int $is_trans разрешить(1) или нет(0) идентификатору в теге masterwmid переводы средств по доверию с доверяемого кошелька purse
     * @param bool|int $is_purse разрешить(1) или нет(0) идентификатору в теге masterwmid просмотр баланса на доверяемом кошельке purse
     * @param bool|int $is_transhist разрешить(1) или нет(0) идентификатору в теге masterwmid просмотр истории операций кошелька purse
     * @param string $masterwmid WMID, которому мы данным запросом разрешает или запрещает управление своим кошельком slavepurse
     * @param float|int $limit суточный лимит
     * @param float|int $daylimit дневной лимит
     * @param float|int $weeklimit недельный лимит
     * @param float|int $monthlimit месячный лимит
     * @return array
     * @throws Exception
     */
    public function xml153($purse, $is_inv = 0, $is_trans = 0, $is_purse = 0, $is_transhist = 0, $masterwmid, $limit = 0, $daylimit = 0, $weeklimit = 0, $monthlimit = 0)
    {
        $data = [
            'purse'        => $purse,
            'is_inv'       => $is_inv,
            'is_trans'     => $is_trans,
            'is_purse'     => $is_purse,
            'is_transhist' => $is_transhist,
            'masterwmid'   => $masterwmid,
            'limit'        => $limit,
            'daylimit'     => $daylimit,
            'weeklimit'    => $weeklimit,
            'monthlimit'   => $monthlimit
        ];

        $interface = new xml153($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X17 [1], операции с арбитражными контрактами
     * [1]: создание контрактов;
     * [2]: информация об акцептантах;
     * @param  string $name краткое (не более 255 символов) название контракта
     * @param  integer $ctype ctype=1 - контракт с открытым доступом, ctype=2 - контракт с ограниченным доступом
     * @param  string $text собственно текст документа. Для разделения строк в тексте документа используйте: \r\n
     * @param  array $accesslist для ctype=2 - массив WMID участников, которым разрешается акцептовывать данный контракт
     * @return array
     */
    public function xml171($name, $ctype, $text, array $accesslist = [])
    {
        $data = [
            'name'       => $name,
            'ctype'      => $ctype,
            'text'       => $text,
            'accesslist' => $accesslist
        ];

        $interface = new xml171($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X17 [2], операции с арбитражными контрактами
     * [1]: создание контрактов;
     * [2]: информация об акцептантах;
     * @param  integer $contractid номер контракта
     * @return array
     */
    public function xml172($contractid)
    {
        // для получения информации об акцептантах всегда указывать mode=acceptdate
        $mode = 'acceptdate';

        $data = [
            'contractid' => $contractid,
            'mode'       => $mode
        ];

        $interface = new xml172($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * XML: X18, получение деталей операции через merchant.webmoney
     * @param  string $purse ВМ-кошелек получателя платежа
     * @param  integer $number номер платежа
     * @param  string $wmid ВМ-идентификатор получателя или подписи
     * @param  integer $type тип номера платежа
     * @param  string $secret_key секретное слово
     * @return array
     */
    public function xml18($purse, $number, $wmid = null, $type = 0, $secret_key = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'purse'      => $purse,
            'number'     => $number,
            'wmid'       => $wmid,
            'type'       => $type,
            'secret_key' => $secret_key
        ];

        $interface = new xml18($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * UnDocumented: возвращает BL
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function getBl($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid
        ];

        $interface = new bl($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * UnDocumented: возвращает TL
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function getTl($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid
        ];

        $interface = new tl($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * UnDocumented: возвращает информацию о претензиях и отзывах в арбитраже
     * @param  string $wmid доверенный wmid
     * @return array
     */
    public function getClaims($wmid = null)
    {
        $wmid = ($wmid) ? $wmid : $this->keeper->wmid;

        $data = [
            'wmid' => $wmid
        ];

        $interface = new claims($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    /**
     * UnDocumented: возвращает информацию об аттестате, включая bl и количество претензий
     * @param  string $search исковый WMID или кошелёк
     * @param  boolean $is_purse это кошелёк? иначе WMID
     * @return array
     */
    public function getInfo($search = null, $is_purse = false)
    {
        $search = ($search) ? $search : $this->keeper->wmid;

        $data = [
            'search'   => $search,
            'is_purse' => $is_purse
        ];

        $interface = new info($this->keeper);
        $interface->storeData($data);

        try {
            $xml = $this->keeper->sendRequest($interface);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), $e->getCode());
        }

        return $this->success($interface->getData($xml));
    }

    private function success($data)
    {
        return [
            'is_error' => 0,
            'data'     => $data
        ];
    }

    private function fail($message, $code)
    {
        return [
            'is_error'      => 1,
            'error_message' => $message,
            'error_code'    => $code
        ];
    }
}