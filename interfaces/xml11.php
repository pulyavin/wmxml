<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml11 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];

        $sign = $this->keeper->getSign($this->keeper->wmid . $wmid);

        $xml = <<<XML
<w3s.request>
    <wmid>{$this->keeper->wmid}</wmid>
    <passportwmid>{$wmid}</passportwmid>
    <sign>{$sign}</sign>
    <params>
        <dict>0</dict>
        <info>1</info>
        <mode>1</mode>
    </params>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        // собираем список WMID, прикрепленных к данному аттестату
        $wmids = [];
        foreach ($xml->certinfo->wmids->row as $wmid) {
            $wmids[(int)$wmid['wmid']] = [
                'wmid'        => (string)$wmid['wmid'],
                'info'        => (string)$wmid['info'], // Дополнительная информация о WMID.
                'nickname'    => (string)$wmid['nickname'], // Псевдоним (название проекта)
                'datereg'     => new DateTime((string)$wmid['datereg']), // Дата и время (московское) регистрации WMID в системе
                'ctype'       => (int)$wmid['ctype'], // Юридический статус WMID (1 - используется в интересах физического лица, 2 - юридического)
                'companyname' => (string)$wmid['companyname'], // Название компании (Заполняется только для юридических лиц)
                'companyid'   => (string)$wmid['companyid'], // Регистрационный номер компании. ИНН (для российских компаний) КОД ЕГРПОУ (для украинских), Certificate number и т.п.
                'phone'       => (string)$wmid['phone'],
                'email'       => (string)$wmid['email'],
            ];
        }

        // собираем список веб-сайтов аттестата
        $weblists = [];
        foreach ($xml->certinfo->userinfo->weblist->row as $weblist) {
            $weblists[] = [
                'url'        => (string)$weblist['url'],
                'check-lock' => (string)$weblist['check-lock'],
                'ischeck'    => (int)$weblist['ischeck'],
                'islock'     => (int)$weblist['islock'],
            ];
        }

        // собираем дополнительные данные
        $extendeddata = [];
        foreach ($xml->certinfo->userinfo->extendeddata->row as $data) {
            $extendeddata[] = [
                'type'       => (string)$data['type'],
                'account'    => (string)$data['account'],
                'check-lock' => (int)$data['check-lock'],
            ];
        }

        return [
            'fullaccess'   => (int)$xml->fullaccess, // индикатор наличия доступа к закрытым полям аттестата
            // информация об аттестате
            'attestat'     => [
                'tid'          => (int)$xml->certinfo->attestat->row['tid'], // Тип аттестата
                'recalled'     => (int)$xml->certinfo->attestat->row['recalled'], // Информация об отказе в обслуживании
                'datecrt'      => new DateTime((string)$xml->certinfo->attestat->row['datecrt']), // Дата и время (московское) выдачи аттестата
                'dateupd'      => new DateTime((string)$xml->certinfo->attestat->row['dateupd']), // Дата и время (московское) последнего изменения данных
                'regnickname'  => (string)$xml->certinfo->attestat->row['regnickname'], // Название проекта, имя (nick) аттестатора, выдавшего данный аттестат
                'regwmid'      => (string)$xml->certinfo->attestat->row['regwmid'], // WMID аттестатора, выдавшего данный аттестат
                'status'       => (int)$xml->certinfo->attestat->row['status'], // признак прохождения вторичной проверки (10 - не пройдена, 11 - пройдена)
                'is_secondary' => (((int)$xml->certinfo->attestat->row['status']) == "11") ? true : false, // признак прохождения вторичной проверки
                'notary'       => (int)$xml->certinfo->attestat->row['notary'], // особенность получения аттестата (0 - личная встреча, 1 - нотариально заверенные документы, 2 - автоматически, по результатам успешного пополнения кошелька)
            ],
            // список WMID, прикрепленных к данному аттестату
            'wmids'        => $wmids,
            // персональные данные владельца аттестата
            'userinfo'     => [
                // персональная информация
                'nickname'   => (string)$xml->certinfo->userinfo->value->row['nickname'], // название проекта, имя (nickname)
                'fname'      => (string)$xml->certinfo->userinfo->value->row['fname'], // фамилия
                'iname'      => (string)$xml->certinfo->userinfo->value->row['iname'], // имя
                'oname'      => (string)$xml->certinfo->userinfo->value->row['oname'], // отчество
                'bdate'      => (new DateTime())->setDate((int)$xml->certinfo->userinfo->value->row['byear'], (int)$xml->certinfo->userinfo->value->row['bmonth'], (int)$xml->certinfo->userinfo->value->row['bday']), // дата рождения
                'byear'      => ((int)$xml->certinfo->userinfo->value->row['byear']), // дата рождения (год)
                'phone'      => ((string)$xml->certinfo->userinfo->value->row['phone']),
                'email'      => ((string)$xml->certinfo->userinfo->value->row['email']),
                'web'        => ((string)$xml->certinfo->userinfo->value->row['web']),
                'sex'        => ((int)$xml->certinfo->userinfo->value->row['sex']), // пол
                'icq'        => ((int)$xml->certinfo->userinfo->value->row['icq']),
                'rcountry'   => ((string)$xml->certinfo->userinfo->value->row['rcountry']), // место (страна) постоянной регистрации
                'rcity'      => ((string)$xml->certinfo->userinfo->value->row['rcity']), // место (город) постоянной регистрации
                'rcountryid' => ((int)$xml->certinfo->userinfo->value->row['rcountryid']),
                'rcitid'     => ((int)$xml->certinfo->userinfo->value->row['rcitid']),
                'radres'     => ((string)$xml->certinfo->userinfo->value->row['radres']), // место (полный адрес) постоянной регистрации
                // почтовый адрес
                'country'    => (string)$xml->certinfo->userinfo->value->row['country'], // почтовый адрес - страна
                'city'       => (string)$xml->certinfo->userinfo->value->row['city'], // почтовый адрес - город
                'region'     => (string)$xml->certinfo->userinfo->value->row['region'], // регион, область
                'zipcode'    => (int)$xml->certinfo->userinfo->value->row['zipcode'], // почтовый адрес -индекс
                'adres'      => (string)$xml->certinfo->userinfo->value->row['adres'], // почтовый адрес - улица, дом, квартира
                'countryid'  => (int)$xml->certinfo->userinfo->value->row['countryid'],
                'citid'      => (int)$xml->certinfo->userinfo->value->row['citid'],
                // паспортные данные
                'pnomer'     => (string)$xml->certinfo->userinfo->value->row['pnomer'], // серия и номер паспорта
                'pdate'      => new DateTime((string)$xml->certinfo->userinfo->value->row['pdate']), // дата выдачи паспорта
                'pcountry'   => (string)$xml->certinfo->userinfo->value->row['pcountry'], // государство, выдавшее паспорт
                'pcountryid' => (int)$xml->certinfo->userinfo->value->row['pcountryid'], // код государства, выдавшее паспорт
                'pcity'      => (string)$xml->certinfo->userinfo->value->row['pcity'],
                'pcitid'     => (int)$xml->certinfo->userinfo->value->row['pcitid'],
                'pbywhom'    => (string)$xml->certinfo->userinfo->value->row['pbywhom'], // код или наименование подразделения (органа), выдавшего паспорт
            ],
            // признак проверки персональных данных аттестатором и блокировки публичного отображения персональных данных.
            // 00 - данное поле не проверено аттестатором и не заблокировано владельцем аттестата для публичного показа
            // 01 - данное поле не проверено аттестатором и заблокировано владельцем аттестата для публичного показа
            // 10 - данное поле проверено аттестатором и не заблокировано владельцем аттестата для публичного показа
            // 11 - данное поле проверено аттестатором и заблокировано владельцем аттестата для публичного показа
            'check-lock'   => [
                'ctype'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['ctype'],
                'jstatus'     => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jstatus'],
                'osnovainfo'  => (string)$xml->certinfo->userinfo->{"check-lock"}->row['osnovainfo'],
                'nickname'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['nickname'],
                'infoopen'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['infoopen'],
                'city'        => (string)$xml->certinfo->userinfo->{"check-lock"}->row['city'],
                'region'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['region'],
                'country'     => (string)$xml->certinfo->userinfo->{"check-lock"}->row['country'],
                'adres'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['adres'],
                'zipcode'     => (string)$xml->certinfo->userinfo->{"check-lock"}->row['zipcode'],
                'fname'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['fname'],
                'iname'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['iname'],
                'oname'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['oname'],
                'pnomer'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pnomer'],
                'pdate'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pdate'],
                'pbywhom'     => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pbywhom'],
                'pdateend'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pdateend'],
                'pcode'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pcode'],
                'pcountry'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pcountry'],
                'pcity'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['pcity'],
                'ncountryid'  => (string)$xml->certinfo->userinfo->{"check-lock"}->row['ncountryid'],
                'ncountry'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['ncountry'],
                'rcountry'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['rcountry'],
                'rcity'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['rcity'],
                'radres'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['radres'],
                'bplace'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['bplace'],
                'bday'        => (string)$xml->certinfo->userinfo->{"check-lock"}->row['bday'],
                'inn'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['inn'],
                'name'        => (string)$xml->certinfo->userinfo->{"check-lock"}->row['name'],
                'dirfio'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['dirfio'],
                'buhfio'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['buhfio'],
                'okpo'        => (string)$xml->certinfo->userinfo->{"check-lock"}->row['okpo'],
                'okonx'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['okonx'],
                'jadres'      => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jadres'],
                'jcountry'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jcountry'],
                'jcity'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jcity'],
                'jzipcode'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jzipcode'],
                'bankname'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['bankname'],
                'bik'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['bik'],
                'ks'          => (string)$xml->certinfo->userinfo->{"check-lock"}->row['ks'],
                'rs'          => (string)$xml->certinfo->userinfo->{"check-lock"}->row['rs'],
                'fax'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['fax'],
                'email'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['email'],
                'web'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['web'],
                'phone'       => (string)$xml->certinfo->userinfo->{"check-lock"}->row['phone'],
                'phonehome'   => (string)$xml->certinfo->userinfo->{"check-lock"}->row['phonehome'],
                'phonemobile' => (string)$xml->certinfo->userinfo->{"check-lock"}->row['phonemobile'],
                'icq'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['icq'],
                'jabberid'    => (string)$xml->certinfo->userinfo->{"check-lock"}->row['jabberid'],
                'sex'         => (string)$xml->certinfo->userinfo->{"check-lock"}->row['sex'],
            ],
            // список веб-сайтов аттестата
            'weblist'      => $weblists,
            // дополнительные данные
            'extendeddata' => $extendeddata,
        ];
    }

    public function getUrlClassic()
    {
        return "https://passport.webmoney.ru/asp/XMLGetWMPassport.asp";
    }

    public function getUrlLight()
    {
        return "https://apipassport.webmoney.ru/asp/XMLGetWMPassport.asp";
    }

    public function getErrors()
    {
        return [
            "1"  => "запрос не выполнен (неверный формат запроса)",
            "2"  => "запрос не выполнен (неверно указан параметр passportwmid)",
            "4"  => "запрос не выполнен (ошибка при проверке подписи)",
            "11" => "запрос не выполнен (не указан один из параметров)",
        ];
    }
}