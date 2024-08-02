<?php

namespace App\Services;

use App\Http\Logic\NextTransLogic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\Crypt\RSA;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NextTransServices
{

    private $nextTransConfig;
    private $trxId;

    private $logic;

    public function __construct()
    {
        $this->nextTransConfig = (object)config('services.next_trans');
        $this->trxId = Str::uuid()->toString();
        $this->logic = new NextTransLogic();
    }

    public function getBalance()
    {
        try {
            $method = 'GET';
            $uri = '/get-balance';
            $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
            $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);
            $body = [];

            if($token) {
                $header = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

                $response = $this->postApi($method, $fullUrl, $header, []);

                if (isset($response['data'])) {
                    return response()->json($response['data']);
                }

                return response()->json(['status' => false, 'message' => 'Unable to Get Balance']);
            }
            return response()->json(['status' => false, 'message' => 'Unable to Proceed with Request To get Balance']);
        } catch (\Exception $ex) {
            return response()->json(['status' => false ,'message' => "Exception :  {$ex->getMessage()}"]);
        }

    }

    public function transfer(Request $request)
    {
        try {
            $trxId = $request->query('trxId');
            $beneficiaryAccountNo = $request->query('Tujuan');
            $amount = $request->query('Nominal');
            $remark = $request->query('Berita');
            $bankCode = $request->query('bank_bic');

            $accInq = $this->accountInquiry($beneficiaryAccountNo, $bankCode);
            $bank = $this->getBankByBicCode($bankCode) ?? '';

            if (isset($accInq['data']['beneficiary_name'])) {
                $response['beneficiary_name'] = $accInq['data']['beneficiary_name'];

                $body = [
                    "bic_code" => $bankCode,
                    "amount" => $amount,
                    "beneficiary_name" => $accInq['data']['beneficiary_name'],
                    "beneficiary_account_no" => $beneficiaryAccountNo,
                    "description" => $remark,
                    'ref_no' => $trxId
                ];

                $insert = $this->logic->insertDisburse($this->trxId, $body);

                if (!$insert['status']) {

                    $data = $this->logic->getDisburseData($trxId);

                    if ($data !== null) {
                        $response = $this->statusDisburse($data->disburse_id);

                        $this->logic->updateStatusDisburse($trxId, $response);

                        $response['ref_no'] = $trxId;

                        if (!empty($bank)) {
                            $response['bank_name'] = $bank['bank_name'];
                        }

                        return response()->json($response);
                    }

                    $body['error_message'] = $insert['message'];
                    $body['trx_id'] = $trxId;
                    $body['created_at'] = date('c');
                    unset($body['ref_no']);
                    return response()->json($body);
                }

                unset($body['ref_no']);

                $response = $this->createDisburse($body);

                if(!isset($response['error_code'])) {
                    $this->logic->updateDisburse($trxId, $response);
                }

                $response['ref_no'] = $trxId;

                if (!empty($bank)) {
                    $response['bank_name'] = $bank['bank_name'];
                }

                return $response;
            }

            return response()->json($accInq);

        }catch (\Exception $ex) {
            return response()->json(['status' => false ,'message' => "Exception :  {$ex->getMessage()}"]);
        }

    }

    public function checkRekening(Request $request)
    {
        try {
            $accountNo = $request->query('account_no');
            $bankCode = $request->query('bic_code');

            $response = $this->accountInquiry($accountNo, $bankCode);

            return response()->json($response);

        } catch (\Exception $ex) {
            return response()->json(['status' => false ,'message' => "Exception :  {$ex->getMessage()}"]);
        }
    }

    public function checkStatus(Request $request)
    {
        try {
            $trxId = $request->query('trxId');

            $data = $this->logic->getDisburseData($trxId);

            if($data !== null) {

                $bank = $this->getBankByBicCode($data->bank_code) ?? '';

                $response = $this->statusDisburse($data->disburse_id);

                $this->logic->updateStatusDisburse($trxId, $response);

                $response['ref_no'] = $trxId;

                if (!empty($bank)) {
                    $response['bank_name'] = $bank['bank_name'];
                }

                return response()->json($response);
            }

            return response()->json(['status' => false ,'message' => "Unable to Find Data with ID {$trxId}"]);

        } catch (\Exception $ex) {
            return response()->json(['status' => false ,'message' => "Exception :  {$ex->getMessage()}"]);
        }
    }

    public function checkKodeBank(Request $request)
    {
        $bankName = $request->has('bank_name') ? $request->query('bank_name') : null;

        $bankList = $this->bankList();

        if(!empty($bankName)) {
            return array_filter($bankList['data'], function ($item) use ($bankName) {
               return stripos($item['bank_name'], $bankName) !== false;
            });
        }

        if(isset($bankList['data'])) {
            return response()->json($bankList['data']);
        }

        return response()->json(['status' => false, 'message' => 'unable to get list of bank']);
    }

    public function callback(Request $request)
    {
        $input = $request->all();

        Log::info('Incoming Callback : '.json_encode($input));

        $updateCallback = $this->logic->updateCallbackDisburse($input);

        Log::info('Callback Validation : '. json_encode($updateCallback));

        return response('OK',200);
    }

    private function createDisburse($body)
    {
        $method = 'POST';
        $uri = '/create-disburse';
        $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
        $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);

        if($token) {

            $prepareHeader = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

            return $this->postApi($method, $fullUrl, $prepareHeader, $body);

        }

        return response()->json(['status' => false, 'message' => 'Unable to Proceed with request']);
    }

    private function statusDisburse(string $disburseId)
    {
        $method = 'POST';
        $uri = '/status-disburse';
        $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
        $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);

        if($token) {

            $body = [
                "information_type" => 'disburse',
                "disburse_id" => $disburseId
            ];

            $prepareHeader = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

            return $this->postApi($method, $fullUrl, $prepareHeader, $body);
        }

        throw new HttpException(403, 'Unable to Proceed with request');
    }

    private function accountInquiry(string $accountNo, string $bankCode)
    {
        $method = 'POST';
        $uri = '/account-inquiry';
        $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
        $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);

        if($token) {

            $body = [
                "beneficiary_account_no" => $accountNo,
                "bic_code" => $bankCode
            ];

            $prepareHeader = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

            return $this->postApi($method, $fullUrl, $prepareHeader, $body);
        }

        throw new HttpException(403, 'Unable to Proceed with request');
    }

    private function getBankByBicCode(string $code)
    {
        $bankList = $this->bankList();

        if (isset($bankList['data'])) {
            $banks = array_filter($bankList['data'], function ($item) use ($code) {
                return $item['bank_bic'] === $code;
            });
            return array_pop($banks) ?? [];
        }

        return null;
    }

    private function bankList()
    {
        $method = 'GET';
        $uri = '/get-bank';
        $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
        $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);

        if($token) {
            $body = [];

            $prepareHeader = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

            return $this->postApi($method, $fullUrl, $prepareHeader, $body);
        }

        return response()->json(['status' => false, 'message' => "Unable to get Bank List"]);
    }

    private function countryList()
    {
        $method = 'GET';
        $uri = '/get-country';
        $fullUrl = $this->nextTransConfig->url.':'.$this->nextTransConfig->port.$uri;
        $token = $this->getCredentials($this->nextTransConfig->client,$this->nextTransConfig->port);

        if($token) {
            $body = [];

            $prepareHeader = $this->getRequestHeader($method, $fullUrl, $token, $body, $this->nextTransConfig->secret);

            return $this->postApi($method, $fullUrl, $prepareHeader, $body);
        }

        throw new HttpException(403, 'Unable to Proceed with request');
    }

    private function getRequestHeader($method, $url, $token, $body, $clientSecret)
    {
        $timestamp = date('c');

        $encodedUri = $this->customUrlencode($url);

        $signature = $this->generateSymmetricSignature($method, $encodedUri, $token, $body, $timestamp, $clientSecret);

        return $this->getSignatureHeader($token, $timestamp, $signature);

    }

    private function postApi($method, $url, $headers, $body)
    {
        try {
            $client = new Client();
            Log::info('Request Header  : '.json_encode($headers));
            Log::info('Request Body  : '.json_encode($body));
            $req = new GuzzleRequest($method,$url,$headers,json_encode($body));
            $results = $client->send($req);
            $response = $results->getBody()->getContents();
            Log::info('Response : '.$response);

            return json_decode($response, true);

        } catch (RequestException $ex) {
            $response = $ex->getResponse()->getBody()->getContents();
            Log::error('Response : '.$response);
            return json_decode($response, true);
        }
    }

    private function getSignatureHeader($token, $timestamp, $signature)
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
            'X-IDEMPOTENCY-KEY' => $this->trxId
        ];
    }

    private function getCredentials($clientId, $port)
    {
//        $timestamp = date('c');
        $timestamp = date('Y-m-d\TH:i:sP');
        $uri = '/access-token';
        $url = env('NEXT_TRANS_URL').':'.$port;
        $fullUrl = $url.$uri;

        $signature = $this->generateAsymmetricSignature($timestamp, $clientId);

        $header = [
            'X-TIMESTAMP' => $timestamp,
            'X-CLIENT-KEY' => $clientId,
            'Content-Type' => 'application/json',
            'X-SIGNATURE' => $signature
        ];

        $body = [
            'grant_type' => "client_credential"
        ];

        $response = $this->postApi('post',$fullUrl,$header, $body);

        return $response['token'] ?? false;
    }

    private function generateAsymmetricSignature($timestamp, $clientId)
    {
        $stringToSign = $clientId .'|'. $timestamp;

        $privateKey = Storage::get(env('NEXT_TRANS_KEY_PATH'));

        // Initialize phpseclib RSA object
        $rsa = RSA::loadPrivateKey($privateKey);
        $padding = $rsa->withPadding(RSA::SIGNATURE_PSS);
        $signature = $padding->withHash('sha256')
            ->withMGFHash('sha256')
            ->withSaltLength(256 - 32 - 2)
            ->sign($stringToSign);

        // Base64 encode the signature for transport
        return base64_encode($signature);
    }

    private function generateSymmetricSignature($method, $uri, $token, $body, $timestamp, $clientSecret)
    {
        if (empty($body)) {
            $body = '{}';
        } else {
            $body = json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $shaBody = hash('sha256', $body);

        $stringToSign = $method.':'.$uri.':'.$token.':'.$shaBody.':'.$timestamp;


        return hash_hmac('sha512', $stringToSign, $clientSecret);
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
