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

    public function getAccountInquiry(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/account-inquiry-internal';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $transactionId = 'ACC'.date('YmdHis').random_int(1000,9999);

        $body = [
            'partnerReferenceNo' => $transactionId,
            'beneficiaryAccountNo' => $request->query('Tujuan')
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body,$this->bankConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->bankConfig->channel,
            'X-PARTNER-ID' => $this->bankConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        Log::info("URL : {$fullUrl}");
        Log::info("Header : ". json_encode($headers));
        Log::info("Body : ". json_encode($body));
        return $this->postApi($method, $fullUrl, $headers, $body);

    }

    public function getBankStatement(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/bank-statement';
        $fullUrl = $this->bankConfig->url.':'.$this->bankConfig->port.$uri;
        $token = $this->getCredentials($this->bankConfig->client,$this->bankConfig->port);
        $transactionId = 'STMT'.date('YmdHis').random_int(1000,9999);
        $startDate = $request->has('StartDate') ? $request->input('StartDate') : date('Y-m-d');
        $endDate = $request->has('EndDate') ? $request->input('EndDate') : date('Y-m-d');
        $accountNumber = $request->has('AccountNumber') ? $request->input('AccountNumber') : $request->query('AccountNumber');
        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);
        $startDateConverted = $startDate->format(DATE_ATOM);
        $endDateConverted = $endDate->format(DATE_ATOM);


        $body = [
            'partnerReferenceNo' => $transactionId,
            'accountNo' => $accountNumber,
            'fromDateTime' => $startDateConverted,
            'toDateTime' => $endDateConverted
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->bankConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->bankConfig->channel,
            'X-PARTNER-ID' => $this->bankConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        $result = $this->postApi($method, $fullUrl, $headers, $body);
        $balance = $result['balance'];
        $details = $result['detailData'];

        $response = [
            'startDate' => date('Y-m-d H:i:s', strtotime($balance[0]['startingBalance']['dateTime'])),
            'endDate' => date('Y-m-d H:i:s', strtotime($balance[0]['endingBalance']['dateTime'])),
            'startingBalance' => $balance[0]['startingBalance']['currency'].' '.$balance[0]['startingBalance']['value'],
            'endingBalance' => $balance[0]['endingBalance']['currency'].' '.$balance[0]['endingBalance']['value'],
        ];

        $transactionDetails = [];

        foreach ($details as $data) {
            $remark = preg_replace('/\s+/', ' ', $data['remark']);

            $transactionDetails[] = [
                'transactionDate' => date('Y-m-d H:i:s', strtotime($data['transactionDate'])),
                'transactionAmount' => $data['amount']['currency'].' '.$data['amount']['value'],
                'type' => $data['type'],
                'remark' => $remark
            ];
        }

        $response['data'] = $transactionDetails;

        return response()->json($response);
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

        $checkTrx = $this->bca_transaction->findByTrxId($transactionId);

        if (empty($checkTrx)) {
            $this->bca_transaction->insert($insertData);

            $results = $this->postApi($method, $fullUrl, $headers, $body);

            $this->bca_transaction->update($results);

            return $results;
        }

        return ['status' => 'error' ,'message' => "Transaksi ReferenceID {$transactionId} sudah pernah di buat sebelumnya. Silahkan ubah ReferenceId"];


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

    public function sendTransferToVAInbound(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-va/payment-intrabank';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $transactionId = $request->query('trxid');
        $amountValue = number_format($request->query('amount'),2,'.', '');
        $inqReqId = 'INQ'.date('YmdHis').random_int(1000,9999);
        $currency = $request->query('currency');

        $partnerServiceId = env('BCA_PARTNER_SERVICE_ID');
        $paddedPartnerServiceId = str_pad($partnerServiceId, 8,'0',STR_PAD_LEFT);
        $customerNo = $request->query('customer_number');
        $customerName = $request->has('customer_email') ? $request->query('customer_name') : null;
        $customerEmail = $request->has('customer_email') ? $request->query('customer_email') : null;
        $customerPhoneNo = $request->has('customer_phone_no') ? $request->query('customer_phone_no') : null;
        $sourceAccNo = $request->has('source_account_number') ? $request->query('source_account_number') : null;
        $sourceAccType = $request->has('source_account_type') ? $request->query('source_account_type') : null;

        $virtualAccNo = $paddedPartnerServiceId.$customerNo;

        $body = [
            'partnerServiceId' => $paddedPartnerServiceId,
            'customerNo' => $customerNo,
            'referenceNo' => $transactionId,
            'virtualAccountNo' => $virtualAccNo,
            'virtualAccountName' => $customerName,
            "paidAmount" => [
                "value" => $amountValue,
                "currency" => $currency
            ],
            "trxDateTime" => date('c'),
//            'inquiryRequestId' => $inqReqId,
            'partnerReferenceNo' => $transactionId,
            'sourceAccountNo' => env('BCA_SOURCE_VA_ACC_NO')
        ];

        if (!empty($customerEmail)) {
            $body['virtualAccountEmail'] = $customerEmail;
        }

        if (!empty($customerPhoneNo)) {
            $body['virtualAccountPhone'] = $customerPhoneNo;
        }

        if (!empty($sourceAccNo)) {
            $body['sourceAccountNo'] = $sourceAccNo;
        }

        if (!empty($sourceAccType)) {
            $body['sourceAccountType'] = $sourceAccType;
        }

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

        Log::info('Headers : '.json_encode($headers));
        Log::info('Body : '.json_encode($body));
        Log::info('URL : '.$fullUrl);

        if (isset($result['responseCode']) && $result['responseCode'] !== '4013301') {
            $this->bca_transaction->update($result, $transactionId);
        }

        return $result;

    }

    public function transferVAInquiryInbound(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-va/inquiry-intrabank';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $dataTransaction = $this->bca_transaction->findByTrxId($request->query('trxid'));
        $inqReqId = 'VAINQ'.date('YmdHis').random_int(1000,9999);

        if(!empty($dataTransaction)) {
            $customerNo = substr($dataTransaction->beneficiary_account_no,8);
            $serviceId = substr($dataTransaction->beneficiary_account_no,0,8);
            $amountValue = number_format($dataTransaction->amount,2,'.', '');
            $currency = $dataTransaction->currency;
            $virtualAccNo = $serviceId.$customerNo;

            $body = [
                'partnerServiceId' => $serviceId,
                'partnerReferenceNo' => $inqReqId,
                'customerNo' => $customerNo,
                'virtualAccountNo' => $virtualAccNo,
                'trxDateTime' => date('c'),
                "amount" => [
                    "value" => $amountValue,
                    "currency" => $currency
                ],
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

    public function transferVAInquiryOutbound(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/v1.0/transfer-va/inquiry';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $inqReqId = 'VAINQOUT'.date('YmdHis').random_int(1000,9999);


        $customerNo = $request->input('customer_no');
        $partnerServiceId = env('BCA_PARTNER_SERVICE_ID');
        $paddedPartnerServiceId = str_pad($partnerServiceId, 8,' ',STR_PAD_LEFT);
        $amountValue = number_format($request->input('amount'),2,'.', '');
        $currency = $request->input('currency');
        $virtualAccNo =$paddedPartnerServiceId.$customerNo;
        $channelCode = 0000;
        $languageId = 'ID';

        $body = [
            'partnerServiceId' => $paddedPartnerServiceId,
            'customerNo' => $customerNo,
            'virtualAccountNo' => $virtualAccNo,
            'trxDateInit' => date('c'),
            'channelCode' => $channelCode,
            'language' => $languageId,
            "amount" => [
                "value" => $amountValue,
                "currency" => $currency
            ],
            'inquiryRequestId' => $inqReqId
        ];

        $prepareHeader = $this->getSnapHeader($method, $fullUrl, $token, $body, $this->vaConfig->secret);

        $additionalHeader = [
            'CHANNEL-ID' => $this->vaConfig->channel,
            'X-PARTNER-ID' => $this->vaConfig->partner
        ];

        $headers = array_merge($prepareHeader, $additionalHeader);

        $results = $this->postApi($method, $fullUrl, $headers, $body);

        Log::info('Headers : '.json_encode($headers));
        Log::info('Body : '.json_encode($body));
        Log::info('URL : '.$fullUrl);

        return $results;

    }

    public function sendSharedBillerPaymentVA(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/shared-biller/v1.0/transfer-va/payment-intrabank';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $transactionId = $request->query('trxid');
        $amountValue = number_format($request->query('amount'),2,'.', '');
        $currency = $request->query('currency');

        $partnerServiceId = env('BCA_PARTNER_SERVICE_ID');
        $paddedPartnerServiceId = str_pad($partnerServiceId, 8,' ',STR_PAD_LEFT);
        $customerNo = $request->query('customer_number');
        $billNo = $request->has('bill_no') ? $request->query('bill_no') : null;

        $virtualAccNo = $paddedPartnerServiceId.$customerNo;

        $body = [
            'partnerServiceId' => $paddedPartnerServiceId,
            'virtualAccountNo' => $virtualAccNo,
            "paidAmount" => [
                "value" => $amountValue,
                "currency" => $currency
            ],
            "trxDateTime" => date('c'),
            'partnerReferenceNo' => $transactionId,
            'sourceAccountNo' => env('BCA_SOURCE_VA_ACC_NO')
        ];

        if (!empty($billNo)) {
            $body['billDetails'] = [
                'billNo' => $billNo
            ];
        }

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

        Log::info('Headers : '.json_encode($headers));
        Log::info('Body : '.json_encode($body));
        Log::info('URL : '.$fullUrl);

        if (isset($result['responseCode']) && $result['responseCode'] !== '4013301') {
            $this->bca_transaction->update($result, $transactionId);
        }

        return $result;

    }

    public function sendSharedBillerVAInquiry(Request $request)
    {
        $method = 'POST';
        $uri = '/openapi/shared-biller/v1.0/transfer-va/inquiry-intrabank';
        $fullUrl = $this->vaConfig->url.':'.$this->vaConfig->port.$uri;
        $token = $this->getCredentials($this->vaConfig->client,$this->vaConfig->port);
        $dataTransaction = $this->bca_transaction->findByTrxId($request->query('trxid'));
        $inqReqId = 'VAINQ'.date('YmdHis').random_int(1000,9999);

        if(!empty($dataTransaction)) {
            $customerNo = substr($dataTransaction->beneficiary_account_no,8);
            $serviceId = substr($dataTransaction->beneficiary_account_no,0,8);
            $virtualAccNo = $serviceId.$customerNo;

            $body = [
                'partnerReferenceNo' => $inqReqId,
                'virtualAccountNo' => $virtualAccNo,
                'trxDateTime' => date('c')
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
