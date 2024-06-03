<?php

namespace App\Repositories;

use App\Contracts\Interfaces\BCASnapRepositoryInterfaces;
use App\Models\BcaTransaction;
use Illuminate\Support\Facades\Log;

class BCASnapRepositories implements BCASnapRepositoryInterfaces
{

    public function findByTrxId(string $trxId)
    {
        return BcaTransaction::where('trx_id', $trxId)->first();
    }
    public function insert(array $data): void
    {
        $body = $data['body'] ?? null;
        $header = $data['header'] ?? null;
        $additionalInfo = isset($body['additionalInfo']) ? json_encode($body['additionalInfo']) : '{}';

        $insertProperties = [
            'trx_id' => $body['partnerReferenceNo'] ?? '',
            'external_id' => $header['X-EXTERNAL-ID'] ?? '',
            'amount' => $body['amount']['value'] ?? $body['paidAmount']['value'] ?? 0,
            'currency' => $body['amount']['currency'] ?? 'IDR',
            'source_account_no' => $body['sourceAccountNo'] ?? '',
            'beneficiary_account_no' => $body['beneficiaryAccountNo'] ?? $body['virtualAccountNo'] ?? '',
            'transaction_dt' => $body['transactionDate'] ?? date('c'),
            'additional_info' => $additionalInfo,
            'type' => $data['type'],
            'remark_1' => $data['remark_1'] ?? '',
            'remark_2' => $data['remark_2'] ?? ''
        ];

        BcaTransaction::create($insertProperties);
    }

    public function update(array $data, string $refNo = null): void
    {
        $trxId = $refNo ?? $data['partnerReferenceNo'] ?? $data['virtualAccountData']['partnerReferenceNo'];
        $transaction = BcaTransaction::where('trx_id', $trxId)->first();

        if(!empty($data)) {
            if($data['responseCode'] === '2001700') {
                $transaction->customer_reference = !empty($data['customerReference']) ? $data['customerReference'] : null;
            }
            if(empty($transaction->ref_no)) {
                $transaction->ref_no = $data['referenceNo'] ?? $data['virtualAccountData']['referenceNo'] ?? null;
            }
        }

        $transaction->response_code = $data['responseCode'];
        $transaction->response_message = $data['responseMessage'];
        $transaction->response_body = json_encode($data);
        $transaction->save();
    }
}
