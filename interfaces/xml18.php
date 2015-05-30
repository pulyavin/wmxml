<?php namespace pulyavin\wmxml\interfaces;

use pulyavin\wmxml\Interfaces;
use SimpleXMLElement;

class xml18 extends Interfaces
{
    public function storeData(array $data = [])
    {
        $purse = $data['purse'];
        $number = $data['number'];
        $wmid = $data['wmid'];
        $type = $data['type'];
        $secret_key = $data['secret_key'];

        $sign = null;
        $md5 = null;

        // аутентифицируемся через WMSigner
        if (!$secret_key) {
            $sign = $this->keeper->getSign($wmid . $purse . $number);
        } // иначе используем MD5-алгоритм
        else {
            $md5 = md5($wmid . $purse . $number . $secret_key);
        }

        $xml = <<<XML
<merchant.request>
    <wmid>{$wmid}</wmid>
    <lmi_payee_purse>{$purse}</lmi_payee_purse>
    <lmi_payment_no>{$number}</lmi_payment_no>
    <lmi_payment_no_type>{$type}</lmi_payment_no_type>
    <sign>{$sign}</sign>
    <md5>{$md5}</md5>
</merchant.request>
XML;

        $this->xml = $xml;
    }

    public function getData(SimpleXMLElement $xml)
    {
        return [
            'wmtransid'       => (int)$xml->operation['wmtransid'],
            'wminvoiceid'     => (int)$xml->operation['wminvoiceid'],
            'amount'          => (float)$xml->operation->amount,
            'operdate'        => (string)$xml->operation->operdate,
            'purpose'         => (string)$xml->operation->purpose,
            'pursefrom'       => (string)$xml->operation->pursefrom,
            'wmidfrom'        => (string)$xml->operation->wmidfrom,
            'capitallerflag'  => (int)$xml->operation->capitallerflag,
            'enumflag'        => (int)$xml->operation->enumflag,
            'IPAddress'       => (string)$xml->operation->IPAddress,
            'telepat_phone'   => (string)$xml->operation->telepat_phone,
            'telepat_paytype' => (int)$xml->operation->telepat_paytype,
            'paymer_number'   => (int)$xml->operation->paymer_number,
            'paymer_email'    => (string)$xml->operation->paymer_email,
            'paymer_type'     => (int)$xml->operation->paymer_type,
            'cashier_number'  => (int)$xml->operation->cashier_number,
            'cashier_date'    => (string)$xml->operation->cashier_date,
            'cashier_amount'  => (float)$xml->operation->cashier_amount,
            'sdp_type'        => (int)$xml->operation->sdp_type,
        ];
    }

    public function getUrlClassic()
    {
        return "https://merchant.webmoney.ru/conf/xml/XMLTransGet.asp";
    }

    public function getUrlLight()
    {
        return "https://merchant.webmoney.ru/conf/xml/XMLTransGet.asp";
    }

    public function getErrors()
    {
        return [
            "-100" => "общая ошибка при разборе запроса",
            "-2"   => "merchant.request/wmid is incorrect",
            //"-2"   => "merchant.request/lmi_payee_purse is incorrect",
            //"-2"   => "merchant.request/lmi_payement_no is incorrect",
            "-3"   => "merchant.request/lmi_payee_purse is incorrect",
            //"-2"   => "merchant.request/wmid is incorrect",
            "-6"   => "sign not right",
            "-7"   => "sign not right: PlanStr",
            //"-7"   => "MD5 or SHA256 not right:PlanStr(this planstr without secret_key)",
            "-8"   => "Operation not found, internal error:error code",
            "1"    => "Merchant purse not found:1",
            "3"    => "Please use sign or sha256 method for authentication:3",
            "2"    => "Please use sign or sha256 method for authentication, and specify secret key in merchant service settings:2",
            "4"    => "Merchant wmid not found or security trust for purse is not exists:4",
            "6"    => "Merchant wmid not found or security trust for purse is not exists:6",
            "7"    => "Payment with lmi_payment_no number not found for this merchant purse:7",
            "8"    => "Payment with lmi_payment_no (by merchant orderid number!) not found for this merchant purse",
            "9"    => "Payment with lmi_payment_no number (by merchant orderid number!) found for this merchant purse, but not paid yet!",
            "10"   => "Payment with lmi_payment_no number (by unique webmoney invoice number!) not found for this merchant purse",
            "11"   => "Payment with lmi_payment_no number (by unique webmoney invoice number!) found for this merchant purse, but not paid yet!",
            "12"   => "Payment with lmi_payment_no number (by unique webmoney transact number!) not found for this merchant purse",
            "13"   => "Payment with lmi_payment_no number (by merchant orderid number!) found for this merchant purse, but it already reject!",
            "14"   => "Payment with lmi_payment_no number (by unique webmoney invoice number!) found for this merchant purse, but it already reject!",
        ];
    }
}