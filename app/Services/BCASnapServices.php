<?php

namespace App\Services;

use App\Contracts\Interfaces\BCASnapServicesInterfaces;
use App\Repositories\BCASnapRepositories;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BCASnapServices implements BCASnapServicesInterfaces
{

    private $bankConfig;
    private $vaConfig;
    private $bca_transaction;

    public function __construct()
    {
        $this->bankConfig = (object)config('services.bank');

        $this->vaConfig = (object)config('services.va');

        $this->bca_transaction = new BCASnapRepositories();
    }

    public function getBankBalance(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/balance-inquiry';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $transactionId = 'BAL'.date('YmdHis').random_int(1000,9999);

        $body = [
            'partnerReferenceNo' => $transactionId,
            'accountNo' => $request->query('account_no')
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body,$this->bankConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->bankConfig->channel,
            'X-PARTNER-ID' => $this->bankConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        return $this->postApi($method, $fullUrl, $headers, $body);

    }

    public function getBankStatement(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/bank-statement';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $transactionId = 'STMT'.date('YmdHis').random_int(1000,9999);

        $startDate = new \DateTime($request->query('StartDate'));
        $endDate = new \DateTime($request->query('EndDate'));
        $startDateConverted = $startDate->format(DATE_ATOM);
        $endDateConverted = $endDate->format(DATE_ATOM);

        $body = [
            'partnerReferenceNo' => $transactionId,
            'accountNo' => $request->query('AccountNumber'),
            'fromDateTime' => $startDateConverted,
            'toDateTime' => $endDateConverted
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->bankConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->bankConfig->channel,
            'X-PARTNER-ID' => $this->bankConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        return $this->postApi($method, $fullUrl, $headers, $body);

    }

    public function sendTransferToBCA(Request $request)
    {

        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-intrabank';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $transactionId = $request->query('ReferenceID');
        $beneficiaryAccountNo = $request->query('Tujuan');
        $amountValue = number_format($request->query('Nominal'),2, '.','');
        $currency = $request->query('CurrencyCode');
        $remark1 = $request->query('Berita1');
        $remark2 = $request->query('Berita2');
        $fullRemark = substr($remark1.' '.$remark2,0,36);
        $additionalInfo = $request->query('InfoTambahan');
        $purposeCode = $request->query('TujuanTransaksi');

        $body = [
            "partnerReferenceNo" => $transactionId,
            "amount" => [
                "value" => $amountValue,
                "currency" => $currency
            ],
            "beneficiaryAccountNo" => $beneficiaryAccountNo,
            "remark" => $fullRemark,
            "sourceAccountNo" => env('BCA_SOURCE_ACC_NO'),
            "transactionDate" => date('c'),
            "additionalInfo" => [
                "economicActivity" => "",
                "transactionPurpose" => ""
            ]
        ];

        if($currency !== 'IDR') {
            $body['additionalInfo'] = [
                "economicActivity" => $additionalInfo,
                "transactionPurpose" => $purposeCode
            ];
        }

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->bankConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->bankConfig->channel,
            'X-PARTNER-ID' => $this->bankConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        $insertData = [
            'type' => 'transfer-intrabank',
            'body' => $body,
            'header' => $headers,
            'remark_1' => $remark1,
            'remark_2' => $remark2
        ];

        $this->bca_transaction->insert($insertData);

        $results = $this->postApi($method, $fullUrl, $headers, $body);

        $this->bca_transaction->update($results);

        return $results;

    }

    public function transferInquiryBCA(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer/status';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $dataTransaction = $this->bca_transaction->findByTrxId($request->query('trxid'));

        switch($dataTransaction->type) {
            case "transfer-intrabank": $serviceCode = '17'; break;
            case "transfer-interbank": $serviceCode = '18'; break;
            case "transfer-interbank-rtgs": $serviceCode = '22'; break;
            case "transfer-interbank-skn": $serviceCode = '23'; break;
            case "transfer-va": $serviceCode = '33'; break;
            default: $serviceCode = '17';
        }

        if(!empty($dataTransaction)) {
            $body = [
                "originalPartnerReferenceNo" => $dataTransaction->trx_id,
                "originalReferenceNo" => $dataTransaction->ref_no,
                "originalExternalId" => $dataTransaction->external_id,
                "serviceCode" => $serviceCode,
                "transactionDate" => date('c')
            ];

            $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->bankConfig->secret);

            $additionalHeader = [
                'CHANNEL-ID' => $this->bankConfig->channel,
                'X-PARTNER-ID' => $this->bankConfig->partner
            ];

            if($serviceCode === '33') {
                $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
                $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
                $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->vaConfig->secret);
                $additionalHeader = [
                    'CHANNEL-ID' => $this->vaConfig->channel,
                    'X-PARTNER-ID' => $this->vaConfig->partner
                ];
            }

            $headers = array_merge($prepareHeader, $additionalHeader);

            $results = $this->postApi($method, $fullUrl, $headers, $body);

            $this->bca_transaction->update($results, $dataTransaction->trx_id);

            return $results;
        }

        throw new ModelNotFoundException('Unable to find Record');

    }

    public function sendTransferToVA(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-va/payment-intrabank';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $transactionId = $request->query('trxid');
        $amountValue = number_format($request->query('amount'),2,'.', '');

        $partnerServiceId = env('BCA_PARTNER_SERVICE_ID');
        $paddedPartnerServiceId = str_pad($partnerServiceId, 8,'0',STR_PAD_LEFT);
        $virtualAccNo = $paddedPartnerServiceId.$request->query('customer_number');

        $body = [
            'partnerReferenceNo' => $transactionId,
            'virtualAccountNo' => $virtualAccNo,
            "paidAmount" => [
                "value" => $amountValue,
                "currency" => "IDR"
            ],
            "trxDateTime" => date('c'),
            'sourceAccountNo' => $request->query('source_account_number')
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->vaConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->vaConfig->channel,
            'X-PARTNER-ID' => $this->vaConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        $insertData = [
            'type' => 'transfer-va',
            'body' => $body,
            'header' => $headers
        ];

        $this->bca_transaction->insert($insertData);

        $result =  $this->postApi($method, $fullUrl, $headers, $body);

        $this->bca_transaction->update($result);

        return $result;

    }

    public function transferVAInquiry(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-va/status';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $dataTransaction = $this->bca_transaction->findByTrxId($request->query('trxid'));

        if(!empty($dataTransaction)) {
            $customerNo = substr($dataTransaction->beneficiary_account_no,8);
            $serviceId = substr($dataTransaction->beneficiary_account_no,0,8);

            $body = [
                'partnerServiceId' => $serviceId,
                'customerNo' => $customerNo,
                'virtualAccountNo' => $dataTransaction->beneficiary_account_no,
                'inquiryRequestId' => '202202111031031234500001136963',
                'paymentRequestId' => '202202111031031234500001136963',
                'additionalInfo' => '{}',
            ];

            $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->vaConfig->secret);

            $additionalHeader = [
                'CHANNEL-ID' => $this->vaConfig->channel,
                'X-PARTNER-ID' => $this->vaConfig->partner
            ];

            $headers = array_merge($prepareHeader, $additionalHeader);

            $results = $this->postApi($method, $fullUrl, $headers, $body);

            $this->bca_transaction->update($results, $dataTransaction->trx_id);

            Log::info('Headers : '.json_encode($prepareHeader));
            Log::info('Body : '.json_encode($body));
            Log::info('URL : '.$fullUrl);

            return $results;
        }

        throw new ModelNotFoundException('Unable to find Record');

    }

    private function postApi($method, $url, $headers, $body)
    {
        try {
            $client = new Client();
            $req = new GuzzleRequest($method,$url,$headers,json_encode($body));
            $results = $client->send($req);
            $response = $results->getBody()->getContents();

            return json_decode($response, true);

        } catch (RequestException $ex) {
            return json_decode($ex->getResponse()->getBody()->getContents(), true);
        }
    }

    public function getSnapHeader($method, $url, $token, $body, $clientSecret)
    {
        $timestamp = date('c');

        $encodedUri = $this->customUrlencode($url);

        $signature = $this->generateSymmetricSignature($method, $encodedUri, $token, $body, $timestamp, $clientSecret);

        return $this->getSnapHeaders($token, $timestamp, $signature);

    }

    public function getCredentials($clientId, $port)
    {
        $timestamp = date('c');
        $uri = '/openapi/v1.0/access-token/b2b';
        $url = env('BCA_API_URL').':'.$port;
        $fullUrl = $url.$uri;

        $signature = $this->generateAsymmetricSignature($timestamp, $clientId);

        $header = [
            'X-TIMESTAMP' => $timestamp,
            'X-CLIENT-KEY' => $clientId,
            'Content-Type' => 'application/json',
            'X-SIGNATURE' => $signature
        ];

        $body = json_encode([
            'grantType' => "client_credentials"
        ]);

        try {

            $client = new Client();
            $request = new GuzzleRequest('post',$fullUrl,$header,$body);
            $results = $client->send($request);
            $response = json_decode($results->getBody()->getContents(),true);

            if($response['responseCode'] === '2007300') {
                return $response['accessToken'];
            }

            return false;

        } catch (\Exception $ex) {
            return false;
        }
    }

    private function getSnapHeaders($token, $timestamp, $signature)
    {
        $nonce = time().random_int(10000,99999);

        return [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'X-EXTERNAL-ID' => $nonce
        ];
    }


    private function generateAsymmetricSignature($timestamp, $clientId)
    {
        $privateKey = openssl_pkey_get_private(Storage::get(env('BCA_PRIVATE_KEY_PATH')));

        $stringToSign = $clientId .'|'. $timestamp;

        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function generateSymmetricSignature($method, $uri, $token, $body, $timestamp, $clientSecret)
    {
        if(empty($body)) {
            $body = '';
        } else {
            $body = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $shaBody = hash('sha256', $body);

        $stringToSign = $method.':'.$uri.':'.$token.':'.$shaBody.':'.$timestamp;


        return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
    }

    private function customUrlencode($url)
    {
        // Parse the URL into its components
        $urlParts = parse_url($url);

        // Handle path component
        $path = $urlParts['path'] ?? '';
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));

        // Handle query component
        $query = $urlParts['query'] ?? '';
        parse_str($query, $queryParams);
        ksort($queryParams); // Sort parameters lexicographically
        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        // Reassemble the URL
        if ($query !== '') {
            $path .= '?' . $query;
        }

        return $path;
    }


}
