<?php

namespace App\Http\Controllers;

use App\Services\BCASnapServices;
use Illuminate\Http\Request;

class BCASnapController extends Controller
{
    //
    protected $services;

    public function __construct()
    {
        $this->services = new BCASnapServices();
    }
    public function balance(Request $request)
    {
        return $this->services->getBankBalance($request);
    }

    public function bankStatement(Request $request)
    {
        return $this->services->getBankStatement($request);
    }

    public function transferToVA(Request $request)
    {
        return $this->services->sendTransferToVA($request);
    }

    public function transferToBca(Request $request)
    {
        // $resultTransfer['beneficiaryAccountNo']
        $resultTransfer = $this->services->sendTransferToBCA($request);

        if(isset($resultTransfer['status']) && $resultTransfer['status'] !== true) {
            return response()->json($resultTransfer);
        }

        $request->merge(['StartDate' => date('Y-m-d'), 'EndDate' => date('Y-m-d'), 'AccountNumber' => env('BCA_SOURCE_ACC_NO')]);
        $responseStatement = $this->services->getBankStatement($request);
        $result = json_decode($responseStatement->getContent(), true);
        $berita1 = $request->input('Berita1');
        $berita2 = $request->input('Berita2');

        $berita = $berita1 .' '. $berita2;
        \Log::info('TF : '.json_encode($resultTransfer));

        foreach ($result['data'] as $data) {
            if (isset($data['remark']) && stripos($data['remark'], $berita) !== false) {
                if(empty($resultTransfer['currency'])) {
                    unset($resultTransfer['currency']);
                }
                $resultTransfer['referenceNo'] = $resultTransfer['partnerReferenceNo'];
                $resultTransfer['type'] = $data['type'];
                $resultTransfer['remark'] = $data['remark'];
                $resultTransfer['transactionAmount'] = $data['transactionAmount'];
                unset($resultTransfer['amount']);
            }
        }

        return response()->json($resultTransfer);
    }

    public function transferInquiryBCA(Request $request)
    {
        return $this->services->transferInquiryBCA($request);
    }

    public function transferInquiryVABCA(Request $request)
    {
        return $this->services->transferVAInquiry($request);
    }
}
