<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml14 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmtranid = $data['wmtranid'];
        $amount = $data['amount'];
        $moneybackphone = $data['moneybackphone'];
        $capitallerpursesrc = $data['capitallerpursesrc'];

        $reqn = $this->getReqn();
        $sign = $this->keeper->getSign($reqn . $wmtranid . $amount);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
        <trans>
            <inwmtranid>{$wmtranid}</inwmtranid>
            <amount>{$amount}</amount>
            <moneybackphone>{$moneybackphone}</moneybackphone>
            <capitallerpursesrc>{$capitallerpursesrc}</capitallerpursesrc>
        </trans>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'id'         => (int)$xml->operation['id'],
            'ts'         => (int)$xml->operation['ts'],
            'inwmtranid' => (int)$xml->operation->inwmtranid,
            'pursesrc'   => (string)$xml->operation->pursesrc,
            'pursedest'  => (string)$xml->operation->pursedest,
            'amount'     => (float)$xml->operation->amount,
            'desc'       => (string)$xml->operation->desc,
            'datecrt'    => new DateTime((string)$xml->operation->datecrt),
            'dateupd'    => new DateTime((string)$xml->operation->dateupd),
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLTransMoneyback.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLTransMoneybackCert.asp";
    }

    public function getErrors()
    {
        return [
            "17"  => "недостаточно средств на кошельке для осуществления возврата",
            "50"  => "транзакция inwmtranid не найдена, возможно она была совершена несколько месяцев назад или это транзакция между кредитными кошельками",
            "51"  => "транзакция inwmtranid имеет тип с протекцией (возвращенная или незавершенная), вернуть ее данным интерфейсом нельзя",
            "52"  => "сумма транзакции inwmtranid меньше суммы переданной в теге запроса trans/amount, вернуть сумму больше исходной нельзя",
            "53"  => "прошло более 30 дней с момента совершения транзакции inwmtranid",
            "54"  => "транзакция выполнена с кошельков сервиса PAYMER при помощи ВМ-карты , ВМ-ноты или чека Пеймер, при этом параметр moneybackphone в запросе не был указан и возврат не может быть осуществлен, необходимо получить у покупателя номер мобильного телефона и передать его в moneybackphone , чтобы покупателю был сделан возврат на этот телефон в Сервис WebMoney Check",
            "55"  => "транзакция выполнена через e-invoicing (параметр lmi_sdp_type в resulturl )а moneybackphone в запросе не был указан (при этом тип lmi_sdp_type платежа тако что в системе нет номера телефона покупателя) и возврат не может быть осуществлен, необходимо получить у покупателя номер мобильного телефона и передать его в moneybackphone , чтобы был сделан возврат на этот телефон в Сервис WebMoney Check",
            "56"  => "сумма транзакции inwmtranid меньше суммы переданной в теге запроса trans/amount и сумм , которые возвращались в рамках транзакции inwmtranid ранее",
            "103" => "транзакция с таким значением поля w3s.request/trans/tranid уже выполнялась на полную сумму возврата при первом же вызове",
            "104" => "транзакция с таким значением поля w3s.request/trans/tranid и с такой же частичной суммой возврата уже выполнялась, второй раз можно вызвать частичный возврат в рамках этой исходной транзакции и на эту же сумму не ранее чем через полчаса",
        ];
    }
}