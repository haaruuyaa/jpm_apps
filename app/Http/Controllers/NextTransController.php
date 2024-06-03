<?php

namespace App\Http\Controllers;

use App\Services\BCASnapServices;
use App\Services\NextTransServices;
use Illuminate\Http\Request;

class NextTransController extends Controller
{
    //
    protected $services;

    public function __construct()
    {
        $this->services = new NextTransServices();
    }
    public function balance()
    {
        return $this->services->getBalance();
    }

    public function transfer(Request $request)
    {
        return $this->services->transfer($request);
    }

    public function checkRekening(Request $request)
    {
        return $this->services->checkRekening($request);
    }

    public function checkStatus(Request $request)
    {
        return $this->services->checkStatus($request);
    }

    public function checkKodeBank(Request $request)
    {
        return $this->services->checkKodeBank($request);
    }

    public function bankList()
    {
        return $this->services->bankList();
    }

    public function countryList()
    {
        return $this->services->countryList();
    }


}
