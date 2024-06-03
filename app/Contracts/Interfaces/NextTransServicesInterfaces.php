<?php

namespace App\Contracts\Interfaces;

use Illuminate\Http\Request;

interface NextTransServicesInterfaces
{
    public function getBankBalance(Request $request);
}
