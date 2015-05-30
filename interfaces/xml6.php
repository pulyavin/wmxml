<?php namespace pulyavin\wmxml\interfaces;

use DateTime;
use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml6 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $wmid = $data['wmid'];
        $message = $data['message'];
        $subject = $data['subject'];

        $reqn = $this->getReqn();
        $message = htmlspecialchars(trim($message), ENT_QUOTES);
        $subject = htmlspecialchars(trim($subject), ENT_QUOTES);

        // ебануться, грёбанный полаллен!
        $message_temp = mb_convert_encoding($message, "CP1251", "UTF-8");
        $subject_temp = mb_convert_encoding($subject, "CP1251", "UTF-8");

        $sign = $this->keeper->getSign($wmid . $reqn . $message_temp . $subject_temp);

        $xml = <<<XML
<w3s.request>
    <reqn>{$reqn}</reqn>
    <wmid>{$this->keeper->wmid}</wmid>
    <sign>{$sign}</sign>
    <message>
        <receiverwmid>{$wmid}</receiverwmid>
        <msgsubj>{$subject}</msgsubj>
        <msgtext>{$message}</msgtext>
    </message>
</w3s.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'id'           => (int)$xml->message['id'],
            'receiverwmid' => (string)$xml->message->receiverwmid,
            'msgsubj'      => (string)$xml->message->msgsubj,
            'msgtext'      => (string)$xml->message->msgtext,
            'datecrt'      => new DateTime((string)$xml->message->datecrt),
        ];
    }

    public function getUrlClassic()
    {
        return "https://w3s.webmoney.ru/asp/XMLSendMsg.asp";
    }

    public function getUrlLight()
    {
        return "https://w3s.wmtransfer.com/asp/XMLSendMsgCert.asp";
    }

    public function getErrors()
    {
        return [
            "-2"  => "Неверное значение поля message\\receiverwmid",
            "-12" => "Подпись не верна",
            "6"   => "корреспондент не найден",
            "35"  => "получатель не принимает сообщения от неавторизованных корреспондентов",
            "102" => "Не выполнено условие постоянного увеличения значения параметра w3s.request/reqn",
        ];
    }
}