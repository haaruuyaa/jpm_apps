<?php

namespace App\Contracts\Interfaces;

use Illuminate\Http\Request;

interface BCASnapServicesInterfaces
{
    public function getBankBalance(Request $request);
    public function getBankStatement(Request $request);
    public function sendTransferToVA(Request $request);
    public function sendTransferToBCA(Request $request);
    public function transferInquiryBCA(Request $request);
}
