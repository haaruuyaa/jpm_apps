<?php

namespace App\Http\Logic;

use App\Models\NextTransModel;
use Illuminate\Database\QueryException;

class NextTransLogic
{
    public function insertDisburse(string $id, array $input): array
    {
        try {
            $data = [
                'bank_code' => $input['bic_code'],
                'amount' => $input['amount'],
                'beneficiary_name' => $input['beneficiary_name'],
                'beneficiary_account' => $input['beneficiary_account_no'],
                'description' => $input['description'],
                'trx_id' => $id,
                'ref_no' => $input['ref_no']
            ];

            NextTransModel::create($data);

            return ['status' => true];
        } catch (QueryException $ex) {
            $errorCode = $ex->errorInfo[1];

            if ($errorCode === 1062) {
                return ['status' => false, 'message' => 'Duplicate TrxId Detected, please change the TrxId'];
            }

            return ['status' => false, 'message' => $ex->getMessage()];

        }

    }

    public function getDisburseData(string $id): ?NextTransModel
    {
        return NextTransModel::where('ref_no' , $id)->firstOrFail();
    }

    public function updateDisburse(string $id, array $input): void
    {
        NextTransModel::where('ref_no', $id)->update([
            'ref_id' => $input['ref_id'],
            'disburse_id' => $input['disburse_id'],
            'type' => $input['object'],
            'trans_code' => $input['transaction_code'],
            'transfer_fee' => $input['transfer_fee']
        ]);

    }

    public function updateStatusDisburse(string $id, array $input): void
    {
        NextTransModel::where('ref_no', $id)->update([
            'status' => $input['disburse_status'],
            'reason' => $input['reason']
        ]);
    }

    public function updateCallbackDisburse(array $input): array
    {
        $id = $input['disburse_id'];

        try {
            $dataTrans = NextTransModel::where('disburse_id', $id)->first();

            if (!empty($dataTrans)) {
                NextTransModel::where('disburse_id', $id)->update([
                    'status' => $input['disburse_status'],
                    'reason' => $input['reason']
                ]);

                return ['status' => true];
            }

            return ['status' => false, 'message' => 'unable to find transaction in database'];

        } catch (QueryException $queryException) {
            return ['status' => false, 'message' => $queryException->getMessage()];
        }


    }
}
