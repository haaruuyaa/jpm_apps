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
        return $this->services->sendTransferToBCA($request);
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
