<?php
class wmxml {
    # внутренние константы
    private $wmid;
    private $wmsigner;
    private $pem;
    private $transid;
    # тип WebMoney Keeper: WinPro (Classic) или WebPro (Light)
    private $keeper;
    # хэндлер curl
    private $curl;
    # хранитель возникших ошибок API
    public $error;
    # типы транзакций:
    const TRANSAC_IN = "in"; # входящая транзакция
    const TRANSAC_OUT = "out"; # исходящая транзакция
    # типы переводов
    const OPERTYPE_CLOSE = 0; # обычный (или с протекцией, завершенный успешно)
    const OPERTYPE_PROTECTION = 4; # с протекцией (не завершена)
    const OPERTYPE_BACK = 12; # с протекцией (вернулась)
    # состояния счетов
    const STATE_NOPAY = 0; # не оплачен
    const STATE_PROTECT = 1; # оплачен по протекции
    const STATE_PAID = 2; # оплачен окончательно
    const STATE_DENIED = 3; # отказан

    /**
     * иницализация объекта
     * @param string $keeper тип WebMoney Keeper: WinPro (Classic) или WebPro (Light)
     * @param array $data   конфигурационный массив
     * [
     *   "wmid", # WMID, подписывающего запросы
     *   "wmsigner", # путь до бинарного файла подписчика wmsigner, если для аутентификации используется WM Keeper WinPro (Classic)
     *   "pem", # путь до файла сертификата, если для аутентификации используется WM Keeper WebPro (Light)
     *   "rootca", # путь до корневого сертификата WebMoney Transfer
     *   "transid" # путь до txt-файла, хранящего значение следущего номера транзакции
     *   "connect", # время таймаута открытия соеднения в библиотеке CURL
     *   "timeout" # время таймаута ожидания получения ответа в библиотеке CURL
     * ]
     */
    public function __construct($keeper, array $data) {
        $wmid       = isset($data['wmid']) ? $data['wmid'] : null;
        $wmsigner   = isset($data['wmsigner']) ? $data['wmsigner'] : null;
        $rootca     = isset($data['rootca']) ? $data['rootca'] : null;
        $transid    = isset($data['transid']) ? $data['transid'] : null;
        $pem        = isset($data['pem']) ? $data['pem'] : null;
        # настройки CURL-бибилотеки
        $connect    = isset($data['connect']) ? $data['connect'] : 5;
        $timeout    = isset($data['timeout']) ? $data['timeout'] : 5;

        # не указано то самое главное...
        if (empty($wmid)) {
            throw new Exception("Unknown WMID");
        }

        # устанавливаем тип WebMoney Keeper
        if ($keeper == "classic") {
            # проверяем корректность путей к файлам
            if (empty($wmsigner)) {
                throw new Exception("Unknown wmsigner-file path");
            }
            if (!realpath($wmsigner)) {
                throw new Exception("Incorrect wmsigner-file path");
            }
            if (empty($rootca)) {
                throw new Exception("Unknown rootca-file path");
            }
            if (!realpath($rootca)) {
                throw new Exception("Incorrect rootca-file path");
            }

            $this->wmsigner = realpath($wmsigner);
        }
        else if ($keeper == "light") {
            if (empty($pem)) {
                throw new Exception("Unknown pem-file path");
            }

            # проверяем корректность путей к файлам
            if (!realpath($pem)) {
                throw new Exception("Incorrect pem-file path");
            }

            $this->pem = realpath($pem);
        }
        else {
            throw new Exception("Incorrect type of WebMoney Keeper");
        }

        # устанавливаем общие параметры
        $this->wmid = $wmid;
        $this->keeper = $keeper;

        # нам указали путь до transid.txt
        if (!empty($transid)) {
            if (!realpath($transid)) {
                throw new Exception("Incorrect transid-file path");
            }

            if (!is_writable(realpath($transid))) {
                throw new Exception("Transid file not writable");
            }

            $this->transid = realpath($transid);
        }

        # инициализиуер объект CURL и настраиваем его
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            # SSL-сертификат
            CURLOPT_CAINFO          => realpath($rootca),
            CURLOPT_SSL_VERIFYPEER  => true,
            # соединяемся и ждём ожидания, не не больше 5 секунд
            CURLOPT_CONNECTTIMEOUT  => $connect,
            CURLOPT_TIMEOUT         => $timeout,
        ]);
    }

    /**
     * XML: X1, выписка счёта
     * @param  string  $wmid       WMID покупателя
     * @param  string  $purse      номер кошелька магазина, на который необходимо оплатить счет
     * @param  double  $amount     сумма счета
     * @param  string  $desc       описание товара или услуги
     * @param  integer $orderid    номер счета в системе учета магазина; любое целое число без знака
     * @param  string  $address    адрес доставки товара
     * @param  integer $period     максимально допустимый срок протекции сделки в днях
     * @param  integer $expiration максимально допустимый срок оплаты счета в днях
     * @param  integer $onlyauth   учитывать разрешение получателя
     * @param  integer $shop_id    номер магазина в каталоге Мегасток
     * @return array
     */
    public function xml1($wmid, $purse, $amount, $desc, $orderid = 0, $address = "", $period = 0, $expiration = 7, $onlyauth = 0, $shop_id = 0) {
        $reqn   =   $this->getReqn();
        $desc   =   htmlspecialchars(trim($desc), ENT_QUOTES);
        $address=   htmlspecialchars(trim($address), ENT_QUOTES);
        $amount =   (float) $amount;

        # ебануться, грёбанный полаллен!
        $desc_temp      = mb_convert_encoding($desc, "CP1251", "UTF-8");
        $address_temp   = mb_convert_encoding($address, "CP1251", "UTF-8");

        $sign = $this->getSign($orderid.$wmid.$purse.$amount.$desc_temp.$address_temp.$period.$expiration.$reqn);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <invoice>
                    <orderid>'.$orderid.'</orderid>
                    <customerwmid>'.$wmid.'</customerwmid>
                    <storepurse>'.$purse.'</storepurse>
                    <amount>'.$amount.'</amount>
                    <desc>'.$desc.'</desc>
                    <address>'.$address.'</address>
                    <period>'.$period.'</period>
                    <expiration>'.$expiration.'</expiration>
                    <onlyauth>'.$onlyauth.'</onlyauth>
                    <lmi_shop_id>'.$shop_id.'</lmi_shop_id>
                </invoice>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("1", $xml);

        return [
            'id'            => (int) $xml->invoice['id'],
            'ts'            => (int) $xml->invoice['ts'],
            'orderid'       => (int) $xml->invoice->orderid,
            'customerwmid'  => (string) $xml->invoice->customerwmid,
            'storepurse'    => (string) $xml->invoice->storepurse,
            'amount'        => (float) $xml->invoice->amount,
            'desc'          => (string) $xml->invoice->desc,
            'address'       => (string) $xml->invoice->address,
            'period'        => (int) $xml->invoice->period,
            'expiration'    => (int) $xml->invoice->expiration,
            'state'         => (int) $xml->invoice->state,
            'datecrt'       => (string) $xml->invoice->datecrt,
            'dateupd'       => (string) $xml->invoice->dateupd,
        ];
    }

    /**
     * XML: X2, отправка перевода
     * @param  string  $at_purse       номер кошелька с которого выполняется перевод (отправитель)
     * @param  string  $to_purse       номер кошелька, на который выполняется перевод (получатель)
     * @param  double  $amount         переводимая сумма
     * @param  string  $desc           описание оплачиваемого товара или услуги
     * @param  integer $protect_period срок протекции сделки в днях
     * @param  string  $protect_code   код протекции сделки
     * @param  integer $wminvid        номер счета (в системе WebMoney), по которому выполняется перевод; целое число > 0; если 0 - перевод не по счету
     * @param  integer $onlyauth       учитывать разрешение получателя
     * @return array
     */
    public function xml2($at_purse, $to_purse, $amount, $desc, $protect_period = 0, $protect_code = "", $wminvid = 0, $onlyauth = 0) {
        $transid    =   $this->getTransid();
        $reqn       =   $this->getReqn();
        $desc       =   htmlspecialchars(trim($desc), ENT_QUOTES);
        $protect_code=  htmlspecialchars(trim($protect_code), ENT_QUOTES);
        $amount     =   floatval($amount);

        # ебануться, грёбанный полаллен!
        $desc_temp      = mb_convert_encoding($desc, "CP1251", "UTF-8");
        $protect_temp   = mb_convert_encoding($protect_code, "CP1251", "UTF-8");

        $sign = $this->getSign($reqn.$transid.$at_purse.$to_purse.$amount.$protect_period.$protect_temp.$desc_temp.$wminvid);
        
        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <trans>
                    <tranid>'.$transid.'</tranid>
                    <pursesrc>'.$at_purse.'</pursesrc>
                    <pursedest>'.$to_purse.'</pursedest>
                    <amount>'.$amount.'</amount>
                    <period>'.$protect_period.'</period>
                    <pcode>'.$protect_code.'</pcode>
                    <desc>'.$desc.'</desc>
                    <wminvid>'.$wminvid.'</wminvid>
                    <onlyauth>'.$onlyauth.'</onlyauth>
                </trans>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("2", $xml);

        return [
            'id'        => (int) $xml->operation['id'],
            'ts'        => (int) $xml->operation['ts'],
            'tranid'    => (int) $xml->operation->tranid,
            'pursesrc'  => (string) $xml->operation->pursesrc,
            'pursedest' => (string) $xml->operation->pursedest,
            'amount'    => (float) $xml->operation->amount,
            'comiss'    => (float) $xml->operation->comiss,
            'opertype'  => (int) $xml->operation->opertype,
            'period'    => (int) $xml->operation->period,
            'wminvid'   => (int) $xml->operation->wminvid,
            'desc'      => (string) $xml->operation->desc,
            'datecrt'   => (string) $xml->operation->datecrt,
            'dateupd'   => (string) $xml->operation->dateupd,
        ];
    }

    /**
     * XML: X3, получение истории операций
     * @param  string  $purse      номер кошелька для которого запрашивается операция
     * @param  string  $datestart  минимальное время и дата выполнения операции (ГГГГММДД ЧЧ:ММ:СС)
     * @param  string  $datefinish максимальное время и дата выполнения операции (ГГГГММДД ЧЧ:ММ:СС)
     * @param  integer $wmtranid   номер операции (в системе WebMoney)
     * @param  integer $transid    номер перевода в системе учета отправителя
     * @param  integer $wminvid    номер счета (в системе WebMoney) по которому выполнялась операция
     * @param  integer $orderid    номер счета в системе учета магазина
     * @return array
     */
    public function xml3($purse, $datestart = null, $datefinish = null, $wmtranid = 0, $transid = 0, $wminvid = 0, $orderid = 0) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($purse.$reqn);

        # устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : date("Ymd h:i:s", strtotime('-3 month'));
        $datefinish = ($datefinish) ? $datefinish : date("Ymd h:i:s");

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <getoperations>
                    <purse>'.$purse.'</purse>
                    <wmtranid>'.$wmtranid.'</wmtranid>
                    <tranid>'.$transid.'</tranid>
                    <wminvid>'.$wminvid.'</wminvid>
                    <orderid>'.$orderid.'</orderid>
                    <datestart>'.$datestart.'</datestart>
                    <datefinish>'.$datefinish.'</datefinish>
                </getoperations>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("3", $xml);

        $operations = [];
        foreach ($xml->operations->operation as $operation) {
            if ($operation->pursesrc == $purse) {
                $type       = self::TRANSAC_OUT;
                $corrpurse  = (string) $operation->pursedest;
            }
            else {
                $type       = self::TRANSAC_IN;
                $corrpurse  = (string) $operation->pursesrc;
            }

            $operations[] = [
                'id'        => (int) $operation['id'],
                'ts'        => (int) $operation['ts'],
                'pursesrc'  => (string) $operation->pursesrc,
                'pursedest' => (string) $operation->pursedest,
                'type'      => $type,
                'corrpurse' => $corrpurse,
                'amount'    => (float) $operation->amount,
                'comiss'    => (float) $operation->comiss,
                'opertype'  => (int) $operation->opertype,
                'wminvid'   => (int) $operation->wminvid,
                'orderid'   => (int) $operation->orderid,
                'tranid'    => (int) $operation->tranid,
                'period'    => (int) $operation->period,
                'desc'      => (string) $operation->desc,
                'datecrt'   => (string) $operation->datecrt,
                'dateupd'   => (string) $operation->dateupd,
                'corrwm'    => (string) $operation->corrwm,
                'rest'      => (float) $operation->rest,
                'timelock'  => (string) $operation->timelock,
            ];
        }

        return $operations;
    }

    /**
     * XML: X4, получение истории выписанных счетов по кошельку. Проверка оплаты счета
     * @param  string  $purse      номер кошелька для оплаты на который выписывался счет
     * @param  string  $datestart  минимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @param  string  $datefinish максимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @param  integer $wminvid    номер счета (в системе WebMoney)
     * @param  integer $orderid    номер счета в системе учета магазина
     * @return array
     */
    public function xml4($purse, $datestart = null, $datefinish = null, $wminvid = 0, $orderid = 0) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($purse.$reqn);

        # устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : date("Ymd h:i:s", strtotime('-3 month'));
        $datefinish = ($datefinish) ? $datefinish : date("Ymd h:i:s");

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <getoutinvoices>
                    <purse>'.$purse.'</purse>
                    <wminvid>'.$wminvid.'</wminvid>
                    <orderid>'.$orderid.'</orderid>
                    <datestart>'.$datestart.'</datestart>
                    <datefinish>'.$datefinish.'</datefinish>
                </getoutinvoices>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("4", $xml);

        $invoices = [];
        foreach ($xml->outinvoices->outinvoice as $invoice) {
            $invoices[] = [
                'id'            => (int) $invoice['id'],
                'ts'            => (int) $invoice['ts'],
                'orderid'       => (int) $invoice->orderid,
                'storepurse'    => (string) $invoice->storepurse,
                'customerwmid'  => (string) $invoice->customerwmid,
                'customerpurse' => (string) $invoice->customerpurse,
                'amount'        => (float) $invoice->amount,
                'datecrt'       => (string) $invoice->datecrt,
                'dateupd'       => (string) $invoice->dateupd,
                'state'         => (int) $invoice->state,
                'address'       => (string) $invoice->address,
                'desc'          => (string) $invoice->desc,
                'period'        => (int) $invoice->period,
                'expiration'    => (int) $invoice->expiration,
                'wmtranid'      => (int) $invoice->wmtranid,
            ];
        }

        return $invoices;
    }

    /**
     * XML: X5, ввод кода протекции для завершения операции с протекцией сделки
     * @param  integer $wmtranid уникальный номер платежа в системе учета WebMoney
     * @param  string $pcode    код протекции сделки
     * @return array
     */
    public function xml5($wmtranid, $pcode) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($wmtranid.$pcode.$reqn);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <finishprotect>
                    <wmtranid>'.$wmtranid.'</wmtranid>
                    <pcode>'.$pcode.'</pcode>
                </finishprotect>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("5", $xml);

        $opertype = (int) $xml->operation->opertype;

        return [
            'id'            => (int) $xml->operation['id'],
            'ts'            => (int) $xml->operation['ts'],
            'orderid'       => $opertype,
            'customerwmid'  => (string) $xml->operation->dateupd,
            'success'       => (!$opertype) ? true : false,
        ];
    }

    /**
     * XML: X6, отправка сообщений
     * @param  string $wmid    WM-идентификатор получателя сообщения
     * @param  string $message текст сообщения
     * @param  string $subject текст сообщения
     * @return array
     */
    public function xml6($wmid, $message, $subject = "") {
        $reqn   =   $this->getReqn();
        $message=   htmlspecialchars(trim($message), ENT_QUOTES);
        $subject=   htmlspecialchars(trim($subject), ENT_QUOTES);

        # ебануться, грёбанный полаллен!
        $message_temp   = mb_convert_encoding($message, "CP1251", "UTF-8");
        $subject_temp   = mb_convert_encoding($subject, "CP1251", "UTF-8");

        $sign   =   $this->getSign($wmid.$reqn.$message_temp.$subject_temp);
        
        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <message>
                    <receiverwmid>'.$wmid.'</receiverwmid>
                    <msgsubj>'.$subject.'</msgsubj>
                    <msgtext>'.$message.'</msgtext>
                </message>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("6", $xml);

        return [
            'id'            => (int) $xml->message['id'],
            'receiverwmid'  => (string) $xml->message->receiverwmid,
            'msgsubj'       => (string) $xml->message->msgsubj,
            'msgtext'       => (string) $xml->message->msgtext,
            'datecrt'       => (string) $xml->message->datecrt,
        ];
    }

    /**
     * XML: X8, проверка существований и принадлежности wmid и кошелька
     * @param  string $wmid  проверяемый на существование wmid
     * @param  string $purse проверяемый на существование кошелёк
     * @return array
     */
    public function xml8($wmid = null, $purse = null) {
        $reqn   =   $this->getReqn();
        $sign   =   $this->getSign($wmid.$purse);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <testwmpurse>
                    <wmid>'.$wmid.'</wmid>
                    <purse>'.$purse.'</purse>
                </testwmpurse>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("8", $xml);

        # проверяем существование wmid
        if (
            $wmid
            &&
            $purse == null
        ) {
            $return = [
                'wmid'  => [
                    'exists'                => ($xml->retval == "1") ? true : false,
                    'available'             => (int) $xml->testwmpurse->wmid['available'],
                    'themselfcorrstate'     => (int) $xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'              => (int) $xml->testwmpurse->wmid['newattst'],
                ],
            ];
        }
        # проверяем существование кошелька
        else if (
            $wmid == null
            &&
            $purse
        ) {
            $exists = ($xml->retval == "1") ? true : false;
            $return = [];

            if ($exists) {
                $return['wmid'] = [
                    'wmid'                  => (string) $xml->testwmpurse->wmid,
                    'available'             => ($xml->testwmpurse->wmid['available'] == "-1") ? false : true,
                    'themselfcorrstate'     => (int) $xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'              => (int) $xml->testwmpurse->wmid['newattst'],
                ];
            }

            $return['purse'] = [
                'exists'                => $exists,
                'merchant_active_mode'  => (int) $xml->testwmpurse->purse['merchant_active_mode'],
                'merchant_allow_cashier'=> (int) $xml->testwmpurse->purse['merchant_allow_cashier'],
            ];
        }
        # проверяем принадлежность кошелька к wmid
        else {
            $belongs = ( ((string) $xml->testwmpurse->purse) == $purse) ? true : false;
            $return = [];

            $return = [
                'wmid' => [
                    'available'             => ($xml->testwmpurse->wmid['available'] == "-1") ? false : true,
                    'themselfcorrstate'     => (int) $xml->testwmpurse->wmid['themselfcorrstate'],
                    'newattst'              => (int) $xml->testwmpurse->wmid['newattst'],
                ],
                'belongs' => $belongs,
            ];

            if ($belongs) {
                $return['purse'] = [
                    'merchant_active_mode'  => (int) $xml->testwmpurse->purse['merchant_active_mode'],
                    'merchant_allow_cashier'=> (int) $xml->testwmpurse->purse['merchant_allow_cashier'],
                ];
            }
        }

        return $return;
    }

    /**
     * XML: X9, получение балансов по кошелькам
     * @param  string $wmid доверенный wmid
     * @return array
     */
    public function xml9($wmid = null) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($this->wmid.$reqn);
        # если не передан WMID - используем системный
        $wmid = ($wmid) ? $wmid : $this->wmid ;

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <getpurses>
                    <wmid>'.$wmid.'</wmid>
                </getpurses>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("9", $xml);

        $purses = [];
        foreach ($xml->purses->purse as $purse) {
            $pursename = (string) $purse->pursename;
            $purses[$pursename] = [
                'id'                => (int) $purse['id'],
                'pursename'         => $pursename,
                'amount'            => (float) $purse->amount,
                'desc'              => (string) $purse->desc,
                'outsideopen'       => (int) $purse->outsideopen,
                'outsideopenstate'  => (int) $purse->outsideopenstate,
                'lastintr'          => (int) $purse->lastintr,
                'lastouttr'         => (int) $purse->lastouttr,
            ];
        }

        return $purses;
    }

    /**
     * XML: X10, получение списка счетов на оплату
     * @param  [type]  $wmid       WM-идентификатор, которому был выписан счет (счета) на оплату
     * @param  integer $wminvid    номер счета (в системе WebMoney)
     * @param  [type]  $datestart  минимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @param  [type]  $datefinish максимальное время и дата создания счета (ГГГГММДД ЧЧ:ММ:СС)
     * @return array
     */
    public function xml10($wmid = null, $wminvid = 0, $datestart = null, $datefinish = null) {
        $wmid = ($wmid) ? $wmid : $this->wmid;
        # устанавливаем даты, если они не переданы
        $datestart = ($datestart) ? $datestart : date("Ymd H:i:s", strtotime('-3 month'));
        $datefinish = ($datefinish) ? $datefinish : date("Ymd H:i:s");

        $reqn = $this->getReqn();
        $sign = $this->getSign($wmid.$wminvid.$datestart.$datefinish.$reqn);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <getininvoices>
                    <wmid>'.$wmid.'</wmid>
                    <wminvid>'.$wminvid.'</wminvid>
                    <datestart>'.$datestart.'</datestart>
                    <datefinish>'.$datefinish.'</datefinish>
                </getininvoices>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("10", $xml);

        $invoices = [];
        foreach ($xml->ininvoices->ininvoice as $invoice) {
            $invoices[] = [
                'id'            => (int) $invoice['id'],
                'ts'            => (int) $invoice['ts'],
                'orderid'       => (int) $invoice->orderid,
                'storewmid'     => (string) $invoice->storewmid,
                'storepurse'    => (string) $invoice->storepurse,
                'amount'        => (float) $invoice->amount,
                'datecrt'       => (string) $invoice->datecrt,
                'dateupd'       => (string) $invoice->dateupd,
                'state'         => (int) $invoice->state,
                'address'       => (string) $invoice->address,
                'desc'          => (string) $invoice->desc,
                'period'        => (int) $invoice->period,
                'expiration'    => (int) $invoice->expiration,
                'wmtranid'      => (int) $invoice->wmtranid,
            ];
        }

        return $invoices;
    }

    /**
     * XML: X11, получение информации из аттестата владельца по WM-идентификатору
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function xml11($wmid = null) {
        $wmid = ($wmid) ? $wmid : $this->wmid;

        $reqn = $this->getReqn();
        $sign = $this->getSign($this->wmid.$wmid);

        $xml = '
            <w3s.request>
                <wmid>'.$this->wmid.'</wmid>
                <passportwmid>'.$wmid.'</passportwmid>
                <sign>'.$sign.'</sign>
                <params>
                    <dict>0</dict>
                    <info>1</info>
                    <mode>1</mode>
                </params>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("11", $xml);

        # собираем список WMID, прикрепленных к данному аттестату
        $wmids = [];
        foreach ($xml->certinfo->wmids->row as $wmid) {
            $wmids[(int) $wmid['wmid']] = [
                'wmid'          => (string) $wmid['wmid'],
                'info'          => (string) $wmid['info'], # Дополнительная информация о WMID.
                'nickname'      => (string) $wmid['nickname'], # Псевдоним (название проекта)
                'datereg'       => (string) $wmid['datereg'], # Дата и время (московское) регистрации WMID в системе
                'ctype'         => (int) $wmid['ctype'], # Юридический статус WMID (1 - используется в интересах физического лица, 2 - юридического)
                'companyname'   => (string) $wmid['companyname'], # Название компании (Заполняется только для юридических лиц)
                'companyid'     => (string) $wmid['companyid'], # Регистрационный номер компании. ИНН (для российских компаний) КОД ЕГРПОУ (для украинских), Certificate number и т.п. 
                'phone'         => (string) $wmid['phone'],
                'email'         => (string) $wmid['email'],
            ];
        }

        # собираем список веб-сайтов аттестата
        $weblists = [];
        foreach ($xml->certinfo->userinfo->weblist->row as $weblist) {
            $weblists[] = [
                'url'           => (string) $weblist['url'],
                'check-lock'    => (string) $weblist['check-lock'],
                'ischeck'       => (int) $weblist['ischeck'],
                'islock'        => (int) $weblist['islock'],
            ];
        }

        # собираем дополнительные данные
        $extendeddata = [];
        foreach ($xml->certinfo->userinfo->extendeddata->row as $data) {
            $extendeddata[] = [
                'type'      => (string) $data['type'],
                'account'   => (string) $data['account'],
                'check-lock'=> (int) $data['check-lock'],
            ];
        }

        return [
            'fullaccess'    => (int) $xml->fullaccess, # индикатор наличия доступа к закрытым полям аттестата
            # информация об аттестате
            'attestat'      => [
                'tid'           => (int) $xml->certinfo->attestat->row['tid'], # Тип аттестата
                'recalled'      => (int) $xml->certinfo->attestat->row['recalled'], # Информация об отказе в обслуживании
                'datecrt'       => (string) $xml->certinfo->attestat->row['datecrt'], # Дата и время (московское) выдачи аттестата
                'dateupd'       => (string) $xml->certinfo->attestat->row['dateupd'], # Дата и время (московское) последнего изменения данных
                'regnickname'   => (string) $xml->certinfo->attestat->row['regnickname'], # Название проекта, имя (nick) аттестатора, выдавшего данный аттестат
                'regwmid'       => (string) $xml->certinfo->attestat->row['regwmid'], # WMID аттестатора, выдавшего данный аттестат
                'status'        => (int) $xml->certinfo->attestat->row['status'], # признак прохождения вторичной проверки (10 - не пройдена, 11 - пройдена)
                'is_secondary'  => ( ((int) $xml->certinfo->attestat->row['status']) == "11") ? true : false, # признак прохождения вторичной проверки
                'notary'        => (int) $xml->certinfo->attestat->row['notary'], # особенность получения аттестата (0 - личная встреча, 1 - нотариально заверенные документы, 2 - автоматически, по результатам успешного пополнения кошелька)
            ],
            # список WMID, прикрепленных к данному аттестату
            'wmids'             => $wmids,
            # персональные данные владельца аттестата
            'userinfo'              => [
                # персональная информация
                'nickname'      => (string) $xml->certinfo->userinfo->value->row['nickname'], # название проекта, имя (nickname)
                'fname'         => (string) $xml->certinfo->userinfo->value->row['fname'], # фамилия
                'iname'         => (string) $xml->certinfo->userinfo->value->row['iname'], # имя
                'oname'         => (string) $xml->certinfo->userinfo->value->row['oname'], # отчество
                'bdate_'        => ((int) $xml->certinfo->userinfo->value->row['bday'])."/".((int) $xml->certinfo->userinfo->value->row['bmonth'])."/".((int) $xml->certinfo->userinfo->value->row['byear']), # дата рождения
                'byear'         => ((int) $xml->certinfo->userinfo->value->row['byear']), # дата рождения (год)
                'phone'         => ((string) $xml->certinfo->userinfo->value->row['phone']),
                'email'         => ((string) $xml->certinfo->userinfo->value->row['email']),
                'web'           => ((string) $xml->certinfo->userinfo->value->row['web']),
                'sex'           => ((int) $xml->certinfo->userinfo->value->row['sex']), # пол
                'icq'           => ((int) $xml->certinfo->userinfo->value->row['icq']),
                'rcountry'      => ((string) $xml->certinfo->userinfo->value->row['rcountry']), # место (страна) постоянной регистрации
                'rcity'         => ((string) $xml->certinfo->userinfo->value->row['rcity']), # место (город) постоянной регистрации
                'rcountryid'    => ((int) $xml->certinfo->userinfo->value->row['rcountryid']),
                'rcitid'        => ((int) $xml->certinfo->userinfo->value->row['rcitid']),
                'radres'        => ((string) $xml->certinfo->userinfo->value->row['radres']), # место (полный адрес) постоянной регистрации
                # почтовый адрес
                'country'       => (string) $xml->certinfo->userinfo->value->row['country'], # почтовый адрес - страна
                'city'          => (string) $xml->certinfo->userinfo->value->row['city'], # почтовый адрес - город
                'region'        => (string) $xml->certinfo->userinfo->value->row['region'], # регион, область
                'zipcode'       => (int) $xml->certinfo->userinfo->value->row['zipcode'], # почтовый адрес -индекс
                'adres'         => (string) $xml->certinfo->userinfo->value->row['adres'], # почтовый адрес - улица, дом, квартира
                'countryid'     => (int) $xml->certinfo->userinfo->value->row['countryid'],
                'citid'         => (int) $xml->certinfo->userinfo->value->row['citid'],
                # паспортные данные
                'pnomer'        => (string) $xml->certinfo->userinfo->value->row['pnomer'], # серия и номер паспорта
                'pdate_'        => (string) $xml->certinfo->userinfo->value->row['pdateMMDDYYYY'], # дата выдачи паспорта
                'pcountry'      => (string) $xml->certinfo->userinfo->value->row['pcountry'], # государство, выдавшее паспорт
                'pcountryid'    => (int) $xml->certinfo->userinfo->value->row['pcountryid'], # код государства, выдавшее паспорт
                'pcity'         => (string) $xml->certinfo->userinfo->value->row['pcity'],
                'pcitid'        => (int) $xml->certinfo->userinfo->value->row['pcitid'],
                'pbywhom'       => (string) $xml->certinfo->userinfo->value->row['pbywhom'], # код или наименование подразделения (органа), выдавшего паспорт
            ],
            # признак проверки персональных данных аттестатором и блокировки публичного отображения персональных данных.
            # 00 - данное поле не проверено аттестатором и не заблокировано владельцем аттестата для публичного показа
            # 01 - данное поле не проверено аттестатором и заблокировано владельцем аттестата для публичного показа
            # 10 - данное поле проверено аттестатором и не заблокировано владельцем аттестата для публичного показа
            # 11 - данное поле проверено аттестатором и заблокировано владельцем аттестата для публичного показа
            'check-lock'                => [
                'ctype'         => (string) $xml->certinfo->userinfo->checklock->row['ctype'],
                'jstatus'       => (string) $xml->certinfo->userinfo->checklock->row['jstatus'],
                'osnovainfo'    => (string) $xml->certinfo->userinfo->checklock->row['osnovainfo'],
                'nickname'      => (string) $xml->certinfo->userinfo->checklock->row['nickname'],
                'infoopen'      => (string) $xml->certinfo->userinfo->checklock->row['infoopen'],
                'city'          => (string) $xml->certinfo->userinfo->checklock->row['city'],
                'region'        => (string) $xml->certinfo->userinfo->checklock->row['region'],
                'country'       => (string) $xml->certinfo->userinfo->checklock->row['country'],
                'adres'         => (string) $xml->certinfo->userinfo->checklock->row['adres'],
                'zipcode'       => (string) $xml->certinfo->userinfo->checklock->row['zipcode'],
                'fname'         => (string) $xml->certinfo->userinfo->checklock->row['fname'],
                'iname'         => (string) $xml->certinfo->userinfo->checklock->row['iname'],
                'oname'         => (string) $xml->certinfo->userinfo->checklock->row['oname'],
                'pnomer'        => (string) $xml->certinfo->userinfo->checklock->row['pnomer'],
                'pdate'         => (string) $xml->certinfo->userinfo->checklock->row['pdate'],
                'pbywhom'       => (string) $xml->certinfo->userinfo->checklock->row[''],
                'pdateend'      => (string) $xml->certinfo->userinfo->checklock->row['pdateend'],
                'pcode'         => (string) $xml->certinfo->userinfo->checklock->row['pcode'],
                'pcountry'      => (string) $xml->certinfo->userinfo->checklock->row['pcountry'],
                'pcity'         => (string) $xml->certinfo->userinfo->checklock->row['pcity'],
                'ncountryid'    => (string) $xml->certinfo->userinfo->checklock->row['ncountryid'],
                'ncountry'      => (string) $xml->certinfo->userinfo->checklock->row['ncountry'],
                'rcountry'      => (string) $xml->certinfo->userinfo->checklock->row['rcountry'],
                'rcity'         => (string) $xml->certinfo->userinfo->checklock->row['rcity'],
                'radres'        => (string) $xml->certinfo->userinfo->checklock->row['radres'],
                'bplace'        => (string) $xml->certinfo->userinfo->checklock->row['bplace'],
                'bday'          => (string) $xml->certinfo->userinfo->checklock->row['bday'],
                'inn'           => (string) $xml->certinfo->userinfo->checklock->row['inn'],
                'name'          => (string) $xml->certinfo->userinfo->checklock->row['name'],
                'dirfio'        => (string) $xml->certinfo->userinfo->checklock->row['dirfio'],
                'buhfio'        => (string) $xml->certinfo->userinfo->checklock->row['buhfio'],
                'okpo'          => (string) $xml->certinfo->userinfo->checklock->row['okpo'],
                'okonx'         => (string) $xml->certinfo->userinfo->checklock->row['okonx'],
                'jadres'        => (string) $xml->certinfo->userinfo->checklock->row['jadres'],
                'jcountry'      => (string) $xml->certinfo->userinfo->checklock->row['jcountry'],
                'jcity'         => (string) $xml->certinfo->userinfo->checklock->row['jcity'],
                'jzipcode'      => (string) $xml->certinfo->userinfo->checklock->row['jzipcode'],
                'bankname'      => (string) $xml->certinfo->userinfo->checklock->row['bankname'],
                'bik'           => (string) $xml->certinfo->userinfo->checklock->row['bik'],
                'ks'            => (string) $xml->certinfo->userinfo->checklock->row['ks'],
                'rs'            => (string) $xml->certinfo->userinfo->checklock->row['rs'],
                'fax'           => (string) $xml->certinfo->userinfo->checklock->row['fax'],
                'email'         => (string) $xml->certinfo->userinfo->checklock->row['email'],
                'web'           => (string) $xml->certinfo->userinfo->checklock->row['web'],
                'phone'         => (string) $xml->certinfo->userinfo->checklock->row['phone'],
                'phonehome'     => (string) $xml->certinfo->userinfo->checklock->row['phonehome'],
                'phonemobile'   => (string) $xml->certinfo->userinfo->checklock->row['phonemobile'],
                'icq'           => (string) $xml->certinfo->userinfo->checklock->row['icq'],
                'jabberid'      => (string) $xml->certinfo->userinfo->checklock->row['jabberid'],
                'sex'           => (string) $xml->certinfo->userinfo->checklock->row['sex'],
            ],
            # список веб-сайтов аттестата
            'weblist'           => $weblists,
            # дополнительные данные
            'extendeddata'      => $extendeddata,
        ];
    }

    /**
     * XML: X13, возврат незавершенного платежа с протекцией
     * @param  integer $wmtranid номер транзакции (целое положительное число) по внутреннему учету WebMoney Transfer (wmtranid)
     * при этом тип этой транзакции должен быть с протекцией (по коду или по времени), а состояние транзакции с протекцией - не завершена
     * @return array
     */
    public function xml13($wmtranid) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($wmtranid.$reqn);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                    <rejectprotect>
                        <wmtranid>'.$wmtranid.'</wmtranid>
                    </rejectprotect>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("13", $xml);

        $opertype = (int) $xml->operation->opertype;

        return [
            'id'            => (int) $xml->operation['id'],
            'ts'            => (int) $xml->operation['ts'],
            'opertype'      => (int) $xml->operation->opertype,
            'dateupd'       => (string) $xml->operation->dateupd,
            'success'       => (!$opertype) ? true : false,
        ];
    }

    /**
     * XML: X14, бескомиссионный возврат средств отправителю (покупателю)
     * @param  integer $wmtranid      номер транзакции (целое положительное число) по внутреннему учету WebMoney Transfer (wmtranid), которую необходимо вернуть, при этом тип этой транзакции должен быть - обычная (opertype=0)
     * @param  double $amount         сумма, которую необходимо вернуть, она не может превышать исходную сумму входящей транзакции
     * @param  integer $moneybackphone телефон покупателя 
     * @param  string $capitallerpursesrc кошелек капиталлера
     * @return array
     */
    public function xml14($wmtranid, $amount, $moneybackphone = null, $capitallerpursesrc = null) {
        $reqn = $this->getReqn();
        $sign = $this->getSign($reqn.$wmtranid.$amount);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                    <trans>
                        <inwmtranid>'.$wmtranid.'</inwmtranid>
                        <amount>'.$amount.'</amount>
                        <moneybackphone>'.$moneybackphone.'</moneybackphone>
                        <capitallerpursesrc>'.$capitallerpursesrc.'</capitallerpursesrc>
                    </trans>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("14", $xml);

        $opertype = (int) $xml->operation->opertype;

        return [
            'id'            => (int) $xml->operation['id'],
            'ts'            => (int) $xml->operation['ts'],
            'inwmtranid'    => (int) $xml->operation->inwmtranid,
            'pursesrc'      => (string) $xml->operation->pursesrc,
            'pursedest'     => (string) $xml->operation->pursedest,
            'amount'        => (float) $xml->operation->amount,
            'desc'          => (string) $xml->operation->desc,
            'datecrt'       => (string) $xml->operation->datecrt,
            'dateupd'       => (string) $xml->operation->dateupd,
        ];
    }


    /**
     * XML: X15 [1], просмотр и изменение текущих настроек управления "по доверию"
     * [1]: получение списка кошельков, управление которыми доверяет, идентификатор, совершающий запрос;
     * [2]: получение списка идентификаторов и их кошельков, которые доверяют, идентификатору, совершающему запрос;
     * [3]: создание или изменение настроек доверия для определённого кошелька или идентификатора;
     * @param  integer $wmtranid      номер транзакции (целое положительное число) по внутреннему учету WebMoney Transfer (wmtranid), которую необходимо вернуть, при этом тип этой транзакции должен быть - обычная (opertype=0)
     * @param  double $amount         сумма, которую необходимо вернуть, она не может превышать исходную сумму входящей транзакции
     * @param  integer $moneybackphone телефон покупателя 
     * @param  string $capitallerpursesrc кошелек капиталлера
     * @return array
     */
    public function xml151() {
        $reqn = $this->getReqn();
        $sign = $this->getSign($this->wmid.$reqn);

        $xml = '
            <w3s.request>
                <reqn>'.$reqn.'</reqn>
                <wmid>'.$this->wmid.'</wmid>
                <sign>'.$sign.'</sign>
                <gettrustlist>
                    <wmid>'.$this->wmid.'</wmid>
                </gettrustlist>
            </w3s.request>
        ';

        # получаем подпарщенный XML-пакет 
        $xml = $this->getObject("151", $xml);

        $trustlist = [];
        foreach ($xml->trustlist->trust as $trust) {
            $trustlist[] = [
                'id'           => (int) $trust['id'],
                'is_inv'       => ((int) $trust['inv']) ? true : false,
                'is_trans'     => ((int) $trust['trans']) ? true : false,
                'is_purse'     => ((int) $trust['purse']) ? true : false,
                'is_transhist' => ((int) $trust['transhist']) ? true : false,
                'purse'        => (string) $trust->purse,
                'daylimit'     => (float) $trust->daylimit,
                'dlimit'       => (float) $trust->dlimit,
                'wlimit'       => (float) $trust->wlimit,
                'mlimit'       => (float) $trust->mlimit,
                'dsum'         => (float) $trust->dsum,
                'wsum'         => (float) $trust->wsum,
                'msum'         => (float) $trust->msum,
                'lastsumdate'  => (string) $trust->lastsumdate,
                'storeswmid'   => (string) $trust->storeswmid,
            ];
        }

        return $trustlist;
    }

    /**
     * UnDocumented: возвращает BL
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function getBl($wmid = null) {
        $wmid = ($wmid) ? $wmid : $this->wmid ;

        $xml = '
            <WMIDLevel.request>
                <signerwmid>'.$this->wmid.'</signerwmid>
                <wmid>'.$wmid.'</wmid>
            </WMIDLevel.request>
        ';

        # получаем подпарщенный XML-пакет
        $xml = $this->getObject("bl", $xml);

        return (int) $xml->level;
    }

    /**
     * UnDocumented: возвращает TL
     * @param  [type] $wmid доверенный wmid
     * @return array
     */
    public function getTl($wmid = null) {
        $wmid = ($wmid) ? $wmid : $this->wmid ;

        $xml = '
            <trustlimits>
                <getlevels>
                    <signerwmid>'.$this->wmid.'</signerwmid>
                    <wmid>'.$wmid.'</wmid>
                </getlevels>
            </trustlimits>
        ';

        # получаем подпарщенный XML-пакет
        $xml = $this->getObject("tl", $xml);

        return (int) $xml->tl['val'];
    }


    /**
     * UnDocumented: возвращает информацию о претензиях и отзывах в арбитраже
     * @param  string $wmid доверенный wmid
     * @return array
     */
    public function getClaims($wmid = null) {
        $wmid = ($wmid) ? $wmid : $this->wmid ;

        $xml = '
            <request>
                <wmid>'.$wmid.'</wmid>
            </request>
        ';

        # получаем подпарщенный XML-пакет
        $xml = $this->getObject("claims", $xml);

        return $xml;
    }

    /**
     * UnDocumented: возвращает информацию об аттестате, включая bl и количество претензий
     * @param  string  $search   исковый WMID или кошелёк
     * @param  boolean $is_purse это кошелёк? иначе WMID
     * @return array
     */
    public function getInfo($search = null, $is_purse = false) {
        $search = ($search) ? $search : $this->wmid ;

        $xml = '
            <request>
                ';
                # мы проверяем кошелёк
                if ($is_purse) {
                    $xml .= '<purse>'.$search.'</purse>';
                }
                # мы проверяем wmid
                else {
                    $xml .= '<wmid>'.$search.'</wmid>';
                }
            $xml .= '               
            </request>
        ';

        # получаем подпарщенный XML-пакет
        $xml = $this->getObject("wminfo", $xml);

        # собираем список wmid's
        $wmids = [];
        foreach ($xml->certinfo->wmids->row as $data) {
            $wmids[] = [
                'wmid'      => (string) $data['wmid'],
                'level'     => (int) $data['level'],
                'datereg'   => (string) $data['datereg'],
            ];
        }

        # собираем список userinfo's
        $userinfo = [];
        foreach ($xml->certinfo->userinfo->value->row as $data) {
            $userinfo[] = [
                'fname' => (string) $data['fname'],
                'iname' => (string) $data['iname'],
                'oname' => (string) $data['oname'],
                'name'  => (string) $data['name'],
            ];
        }

        # собираем список claims's
        $claims = [];
        foreach ($xml->certinfo->claims->row as $data) {
            $claims[] = [
                'posclaimscount'    => (int) $data['posclaimscount'],
                'negclaimscount'    => (int) $data['negclaimscount'],
                'claimslastdate'    => (string) $data['claimslastdate'],
            ];
        }

        # собираем список urls's
        $urls = [];
        foreach ($xml->certinfo->urls->row as $data) {
            $urls[] = [
                'attestaturl'           => (string) $data['attestaturl'],
                'attestaticonurl'       => (string) $data['attestaticonurl'],
                'attestatsmalliconurl'  => (string) $data['attestatsmalliconurl'],
                'claimsurl'             => (string) $data['claimsurl'],
            ];
        }

        return [
            'tid'       => (int) $xml->certinfo->attestat->row['tid'],
            'typename'  => (string) $xml->certinfo->attestat->row['typename'],
            'wmids'     => $wmids,
            'userinfo'  => $userinfo,
            'claims'    => $claims,
            'urls'      => $urls,
        ];
    }

    /**
     * отправляет запрос к API через CURL
     * @param  string $interface URL-адрес интерфейса
     * @param  string $xml       отправляемый XML-пакет
     * @return string
     */
    private function sendXml($interface, $xml) {
        curl_setopt_array($this->curl, [
            CURLOPT_URL             => $this->getUrl($interface),
            CURLOPT_POSTFIELDS      => $xml,
        ]);

        # обрабатываем ошибку cURL
        if (($result = curl_exec($this->curl)) === false) {
            throw new Exception(curl_error($this->curl).print_r(curl_getinfo($this->curl), true), curl_errno($this->curl));
        }

        if (empty($result)) {
            throw new Exception("Not incomming xml data");
        }

        return $result;
    }

    /**
     * обращается к API, получает XML, распарсивает его и возвращает
     * @param  string $interface URL-адрес интерфейса
     * @param  string $xml       отправляемый XML-пакет
     * @return array
     */
    private function getObject($interface, $xml) {
        $result = $this->sendXml($interface, $xml);

        # хак для XML11, не умею я обращаться к свойствам объекта, в которых есть дефис ;)
        if ($interface == "11") {
            $result = str_replace("check-lock", "checklock", $result);
        }

        $xml = simplexml_load_string($result);

        # обрабатываем ошибку
        if (
            $interface == "11"
            ||
            $interface == "wminfo"
        ) {
            $retval = (int) $xml['retval'];
        }
        else {
            $retval = (int) $xml->retval;
        }

        # хак для XML8, всё нормально
        if (
            $interface == "8"
            &&
            $retval == "1"
        ) {
            $retval = "0";
        }

        if ($retval) {
            # пытаемся найти текст ошибки
            $error = $this->getError($interface, $retval);
            # текст не нашли, а может в пакете чё есть...
            if ($error === false && isset($xml->retdesc)) {
                $error = $xml->retdesc;
            }
            $this->error = $error;

            throw new Exception($this->error, $retval);
        }

        return $xml;
    }

    /**
     * возвращает уникальный, увеличивающийся REQN
     * @return integer
     */
    private function getReqn() {
        $time = microtime();
        return substr($time, 11).substr($time, 2, 5);
    }

    /**
     * возвращает и инкрементирует транзакционный идентификатор платежа из файла transid.txt 
     * @return [type] [description]
     */
    private function getTransid() {
        # а вдруг нам не указали путь до файла transid.txt
        if (empty($this->transid)) {
            throw new Exception("Have not path transid-file!");
        }

        $fopen = fopen($this->transid, "r+");

        $start = time(); # время старта попытки блокировки
        # пытаемся получить блокировку в течении 3-х секунд
        while ((time(true) - $start) < 3) {
            $locked = flock($fopen, LOCK_EX);
            # не удалось заблокировать файл
            if (!$locked) {
                # ну чтож, ждём 100мс
                usleep(100000);
            }
        }

        # не уалось получить блокировку
        if (!$locked) {
            throw new Exception("Can't get lock transid file");
        }

        $transid = (int) fgets($fopen);
        $transid++;
        rewind($fopen);
        fwrite($fopen, $transid);
        fflush($fopen);
        flock($fopen, LOCK_UN);
        fclose($fopen);

        return $transid;
    }

    /**
     * пишет в бинарный wmsigner и получает подписанную строку
     * @param  string $string строка для подписи
     * @return string
     */
    private function getSign($string) {
        # если это WebMoney Keeper WebPro, то подписываеть ему ничего не надо
        if ($this->keeper == "light") {
            return;
        }

        # мы должны быть в папке, т.к. там файл ключей и ini-файл
        chdir(dirname($this->wmsigner));

        $descriptorspec = Array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "r")
        );

        $process = proc_open($this->wmsigner, $descriptorspec, $pipes);
        fwrite($pipes[0], "$string\004\r\n");
        fclose($pipes[0]);

        $string = fgets($pipes[1], 133);
        fclose($pipes[1]);
        $return_value = proc_close($process);

        return $string;
    }

    /**
     * по идентификатору возвращает URL-адрес интерфейса
     * @param  string $interface название интерфейса
     * @return string
     */
    private function getUrl($interface) {
        $url = [
            # список URL для WM Keeper WinPro (Classic), использующий аутентификацию через ключи и wmsigner
            "classic" => [
                "1"     =>  "https://w3s.webmoney.ru/asp/XMLInvoice.asp",
                "2"     =>  "https://w3s.webmoney.ru/asp/XMLTrans.asp",
                "3"     =>  "https://w3s.webmoney.ru/asp/XMLOperations.asp",
                "4"     =>  "https://w3s.webmoney.ru/asp/XMLOutInvoices.asp",
                "5"     =>  "https://w3s.webmoney.ru/asp/XMLFinishProtect.asp",
                "6"     =>  "https://w3s.webmoney.ru/asp/XMLSendMsg.asp",
                "7"     =>  "https://w3s.webmoney.ru/asp/XMLClassicAuth.asp",
                "8"     =>  "https://w3s.webmoney.ru/asp/XMLFindWMPurseNew.asp",
                "9"     =>  "https://w3s.webmoney.ru/asp/XMLPurses.asp",
                "10"    =>  "https://w3s.webmoney.ru/asp/XMLInInvoices.asp",
                "11"    =>  "https://passport.webmoney.ru/asp/XMLGetWMPassport.asp",
                "13"    =>  "https://w3s.webmoney.ru/asp/XMLRejectProtect.asp",
                "14"    =>  "https://w3s.webmoney.ru/asp/XMLTransMoneyback.asp",
                "151"   =>  "https://w3s.webmoney.ru/asp/XMLTrustList.asp",
                "152"   =>  "https://w3s.webmoney.ru/asp/XMLTrustList2.asp",
                "153"   =>  "https://w3s.webmoney.ru/asp/XMLTrustSave2.asp",
                "16"    =>  "https://w3s.webmoney.ru/asp/XMLCreatePurse.asp",
                "171"   =>  "https://arbitrage.webmoney.ru/xml/X17_CreateContract.aspx",
                "172"   =>  "https://arbitrage.webmoney.ru/xml/X17_GetContractInfo.aspx",
                "18"    =>  "https://merchant.webmoney.ru/conf/xml/XMLTransGet.asp",
                "19"    =>  "https://apipassport.webmoney.ru/XMLCheckUser.aspx",
                "201"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTransRequest.asp",
                "202"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTransConfirm.asp",
                "211"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTrustRequest.asp",
                "212"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTrustConfirm.asp",
                "22"    =>  "https://merchant.webmoney.ru/conf/xml/XMLTransSave.asp",

                "bl"    =>  "https://stats.wmtransfer.com/levels/XMLWMIDLevel.aspx",
                "tl"    =>  "https://debt.wmtransfer.com/xmlTrustLevelsGet.aspx",
                "claims"    =>  "http://arbitrage.webmoney.ru/asp/XMLGetWMIDClaims.asp",
                "wminfo"    =>  "https://passport.webmoney.ru/xml/XMLGetWMIDInfo.aspx",
            ],

            # список URL для WM Keeper WebPro (Light), использующий аутентификацию через сертификат
            "light" => [
                "1"     =>  "https://w3s.wmtransfer.com/asp/XMLInvoiceCert.asp",
                "2"     =>  "https://w3s.wmtransfer.com/asp/XMLTransCert.asp",
                "3"     =>  "https://w3s.wmtransfer.com/asp/XMLOperationsCert.asp",
                "4"     =>  "://w3s.webmoney.ru/asp/XMLOutInvoices.asp",
                "5"     =>  "https://w3s.wmtransfer.com/asp/XMLFinishProtectCert.asp",
                "6"     =>  "https://w3s.wmtransfer.com/asp/XMLSendMsgCert.asp",
                "7"     =>  "https://w3s.wmtransfer.com/asp/XMLClassicAuthCert.asp",
                "8"     =>  "https://w3s.wmtransfer.com/asp/XMLFindWMPurseCertNew.asp",
                "9"     =>  "https://w3s.wmtransfer.com/asp/XMLPursesCert.asp",
                "10"    =>  "https://w3s.webmoney.ru/asp/XMLInInvoicesCert.asp",
                "11"    =>  "https://apipassport.webmoney.ru/asp/XMLGetWMPassport.asp",
                "13"    =>  "https://w3s.wmtransfer.com/asp/XMLRejectProtectCert.asp",
                "14"    =>  "https://w3s.wmtransfer.com/asp/XMLTransMoneybackCert.asp",
                "151"   =>  "https://w3s.webmoney.ru/asp/XMLTrustListCert.asp",
                "152"   =>  "https://w3s.webmoney.ru/asp/XMLTrustList2Cert.asp",
                "153"   =>  "https://w3s.webmoney.ru/asp/XMLTrustSave2Cert.asp",
                "16"    =>  "https://w3s.wmtransfer.com/asp/XMLCreatePurseCert.asp",
                "171"   =>  "",
                "172"   =>  "",
                "18"    =>  "https://merchant.webmoney.ru/conf/xml/XMLTransGet.asp",
                "19"    =>  "https://apipassportcrt.webmoney.ru/XMLCheckUserCert.aspx",
                "201"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTransRequest.asp",
                "202"   =>  "https://merchant.webmoney.ru/conf/xml/XMLTransConfirm.asp",
                "211"   =>  "https://merchant.wmtransfer.com/conf/xml/XMLTrustRequest.asp",
                "212"   =>  "https://merchant.wmtransfer.com/conf/xml/XMLTrustConfirm.asp",
                "22"    =>  "https://merchant.webmoney.ru/conf/xml/XMLTransSave.asp",

                "bl"    =>  "https://stats.wmtransfer.com/levels/XMLWMIDLevel.aspx",
                "tl"    =>  "https://debt.wmtransfer.com/xmlTrustLevelsGet.aspx",
                "claims"    =>  "http://arbitrage.webmoney.ru/asp/XMLGetWMIDClaims.asp",
                "wminfo"    =>  "https://passport.webmoney.ru/xml/XMLGetWMIDInfo.aspx",
            ]
        ];

        if (!isset($url[$this->keeper][$interface])) {
            throw new Exception("Undefined API URL!");
        }

        if (empty($url[$this->keeper][$interface])) {
            throw new Exception("Incorrect API for this WebMoney Keeper!");
        }

        return $url[$this->keeper][$interface];
    }

    /**
     * по номеру ошибки возвращает её текст
     * @param  string $interface название интерфейса
     * @param  integer $code     код полученной ошибки
     * @return string
     */
    private function getError($interface, $code) {
        $errors = [
            "1" => [
                "-100" => "общая ошибка при разборе команды. неверный формат команды.",
                "-9" => "неверное значение поля w3s.request/reqn",
                "-8" => "неверное значение поля w3s.request/sign",
                "-1" => "неверное значение поля w3s.request/invoice/orderid",
                "-2" => "неверное значение поля w3s.request/invoice/customerwmid",
                "-3" => "неверное значение поля w3s.request/invoice/storepurse",
                "-5" => "неверное значение поля w3s.request/invoice/amount",
                "-6" => "слишком длинное поле w3s.request/invoice/desc",
                "-7" => "слишком длинное поле w3s.request/invoice/address",
                "-11" => "идентификатор, переданный в поле w3s.request/wmid не зарегистрирован",
                "-12" => "проверка подписи не прошла",
                "102" => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
                "110" => "нет прав на использования интерфейса; аттестат не удовлетворяет требованиям",
                "111" => "попытка выставление счета для кошелька не принадлежащего WMID, которым подписывается запрос; при этом доверие не установлено.",
                "5" => "отправитель счета не найден",
                "6" => "получатель счета не найден",
                "7" => "отправитель счета не найден",
                "8" => "кошелек w3s.request/invoice/storepurse принадлежит агрегатору платежей, но lmi_shop_id не указан или указан неверно",
                "35" => "плательщик не авторизован корреспондентом для выполнения данной операции. Это означает, что магазин пытается выписать счет плательщику, который, либо не добавил ВМИД магазина к себе в список корреспондентов и при этом запретил неавторизованным (не являющимся его корреспондентами) выписывать себе счета (для Кипер Классик - в главном меню вверху - Инструменты - Парметры программы -Ограничения ), либо плательщик добавил ВМИД магазина к себе в корреспонденты, но именно для ВМИДа этого магазина запретил выписку себе счетов. Без действий со стороны плательщика избежать этой ошибки магазин не может, необходимо показать плательщику ВМИД магазина с инструкцией о том, что ВМИД магазина должен быть добавлен плательщиком в список корреспондентов и для ВМИДа должна быть разрешена выписка счета",
                "51" => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом имеет лишь аттестат псевдонима, которого недостаточно для приема средств данным автоматизированным способом",
                "52" => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом имеет формальный аттестат у которого нет проверенного телефона и проверенной копии паспорта или ИНН и этого недостаточно для приема средств данным автоматизированным способом",
                "54" => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил дневной лимит на прием средств автоматизированным способом",
                "55" => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил недельный лимит на прием средств автоматизированным способом",
                "56" => "кошелек продавца w3s.request/invoice/storepurse не имеет регистрации в каталоге Мегасток и при этом превысил месячный лимит на прием средств автоматизированным способом",
                "61" => "Превышен лимит долговых обязательств заемщика",
            ],


            "2" => [
                "-100" => "общая ошибка при разборе команды. неверный формат команды.",
                "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
                "-1" => "неверное значение поля w3s.request/reqn",
                "-2" => "неверное значение поля w3s.request/sign",
                "-3" => "неверное значение поля w3s.request/trans/tranid",
                "-4" => "неверное значение поля w3s.request/trans/pursesrc",
                "-5" => "неверное значение поля w3s.request/trans/pursedest",
                "-6" => "неверное значение поля w3s.request/trans/amount",
                "-7" => "неверное значение поля w3s.request/trans/desc",
                "-8" => "слишком длинное поле w3s.request/trans/pcode",
                "-9" => "поле w3s.request/trans/pcode не должно быть пустым если w3s.request/trans/period > 0",
                "-10" => "поле w3s.request/trans/pcode должно быть пустым если w3s.request/trans/period = 0",
                "-11" => "неверное значение поля w3s.request/trans/wminvid",
                "-12" => "идентификатор переданный в поле w3s.request/wmid не зарегистрирован",
                "-14" => "проверка подписи не прошла",
                "-15" => "неверное значение поля w3s.request/wmid",
                "102" => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
                "103" => "транзакция с таким значением поля w3s.request/trans/tranid уже выполнялась",
                "110" => "нет доступа к интерфейсу",
                "111" => "попытка перевода с кошелька не принадлежащего WMID, которым подписывается запрос; при этом доверие не установлено.",
                "5" => "идентификатор отправителя не найден",
                "6" => "корреспондент не найден",
                "7" => "кошелек получателя не найден",
                "11" => "кошелек отправителя не найден",
                "13" => "сумма транзакции должна быть больше нуля",
                "17" => "недостаточно денег в кошельке для выполнения операции",
                "21" => "счет, по которому совершается оплата не найден",
                "22" => "по указанному счету оплата с протекцией не возможна",
                "25" => "время действия оплачиваемого счета закончилось",
                "26" => "в операции должны участвовать разные кошельки",
                "29" => "типы кошельков отличаются",
                "30" => "кошелек не поддерживает прямой перевод",
                "35" => "плательщик не авторизован корреспондентом для выполнения данной операции",
                "58" => "превышен лимит средств на кошельках получателя",
            ],

            "3" => [
                "-100" => "общая ошибка при разборе команды. неверный формат команды.",
                "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
                "-1" => "неверное значение поля w3s.request/wmid",
                "-2" => "неверное значение поля w3s.request/getoperations/purse",
                "-3" => "неверное значение поля w3s.request/sign",
                "-4" => "неверное значение поля w3s.request/reqn",
                "-5" => "проверка подписи не прошла",
                "-7" => "неверное значение поля w3s.request/getoperations/datestart",
                "-8" => "неверное значение поля w3s.request/getoperations/datefinish",
                "-9" => "WMID указанный в поле w3s.request/wmid не найден",
                "102" => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
                "111" => "попытка запроса истории по кошельку не принадлежащему WMID, которым подписывается запрос; при этом доверие не установлено.",
                "1004" => "слишком большой диапазон выборки",
            ],

            "4" => [
                "111" => "попытка запроса информации по кошельку не принадлежащему WMID, которым подписывается запрос; при этом доверие не установлено.",
            ],

            "5" => [
                "20" => "20 - код протекции неверен, но кол-во попыток ввода кода (8) не исчерпано",
            ],

            "6" => [
                "-2" => "Неверное значение поля message\\receiverwmid",
                "-12" => "Подпись не верна",
                "6" => "корреспондент не найден",
                "35" => "получатель не принимает сообщения от неавторизованных корреспондентов",
                "102" => "Не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
            ],

            "8" => [
                "-100" => "общая ошибка при разборе команды. неверный формат команды.",
                "-2" => "неверный WMId для проверки",
            ],

            "11" => [
                "1" => "запрос не выполнен (неверный формат запроса)",
                "2" => "запрос не выполнен (неверно указан параметр passportwmid)",
                "4" => "запрос не выполнен (ошибка при проверке подписи)",
                "11" => "запрос не выполнен (не указан один из параметров)",
            ],

            "13" => [
                "-100" => "общая ошибка при разборе команды. неверный формат команды.",
                "-110" => "запросы отсылаются не с того IP адреса, который указан при регистрации данного интерфейса в Технической поддержке.",
                "-1" => "неверное значение поля w3s.request/reqn",
                "-2" => "неверное значение поля w3s.request/sign",
                "-11" => "неверное значение поля w3s.request/trans/wminvid",
                "-12" => "идентификатор переданный в поле w3s.request/wmid не зарегистрирован",
                "-14" => "проверка подписи не прошла",
                "-15" => "неверное значение поля w3s.request/wmid",
                "102" => "не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
                "103" => "транзакция с таким значением поля w3s.request/trans/tranid уже выполнялась",
                "110" => "нет доступа к интерфейсу",
                "111" => "попытка перевода с кошелька не принадлежащего WMID, которым подписывается запрос; при этом доверие не установлено.",
                "5" => "идентификатор отправителя не найден",
                "6" => "корреспондент не найден",
                "17" => "недостаточно денег в кошельке для выполнения операции",
                "21" => "счет, по которому совершается оплата не найден",
                "22" => "по указанному счету оплата с протекцией не возможна",
                "25" => "время действия оплачиваемого счета закончилось",
                "30" => "кошелек не поддерживает прямой перевод",
                "35" => "плательщик не авторизован корреспондентом для выполнения данной операции",
                "58" => "превышен лимит средств на кошельках получателя",
            ],

            "14" => [
                "17" => "недостаточно средств на кошельке для осуществления возврата",
                "50" => "транзакция inwmtranid не найдена, возможно она была совершена несколько месяцев назад или это транзакция между кредитными кошельками",
                "51" => "транзакция inwmtranid имеет тип с протекцией (возвращенная или незавершенная), вернуть ее данным интерфейсом нельзя",
                "52" => "сумма транзакции inwmtranid меньше суммы переданной в теге запроса trans/amount, вернуть сумму больше исходной нельзя",
                "53" => "прошло более 30 дней с момента совершения транзакции inwmtranid",
                "54" => "транзакция выполнена с кошельков сервиса PAYMER при помощи ВМ-карты , ВМ-ноты или чека Пеймер, при этом параметр moneybackphone в запросе не был указан и возврат не может быть осуществлен, необходимо получить у покупателя номер мобильного телефона и передать его в moneybackphone , чтобы покупателю был сделан возврат на этот телефон в Сервис WebMoney Check",
                "55" => "транзакция выполнена через e-invoicing (параметр lmi_sdp_type в resulturl )а moneybackphone в запросе не был указан (при этом тип lmi_sdp_type платежа тако что в системе нет номера телефона покупателя) и возврат не может быть осуществлен, необходимо получить у покупателя номер мобильного телефона и передать его в moneybackphone , чтобы был сделан возврат на этот телефон в Сервис WebMoney Check",
                "56" => "сумма транзакции inwmtranid меньше суммы переданной в теге запроса trans/amount и сумм , которые возвращались в рамках транзакции inwmtranid ранее",
                "103" => "транзакция с таким значением поля w3s.request/trans/tranid уже выполнялась на полную сумму возврата при первом же вызове",
                "104" => "транзакция с таким значением поля w3s.request/trans/tranid и с такой же частичной суммой возврата уже выполнялась, второй раз можно вызвать частичный возврат в рамках этой исходной транзакции и на эту же сумму не ранее чем через полчаса",
            ],

            "wminfo" => [
                "404" => "Данный идентификатор в системе не зарегистрирован",
            ],
        ];

        return (isset($errors[$interface][$code])) ? $errors[$interface][$code] : false;
    }
}
?>