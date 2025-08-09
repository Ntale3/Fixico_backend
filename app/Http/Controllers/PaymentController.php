<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Http\Request;
use App\Services\MomoService;


class PaymentController extends Controller
{


    public function requestToPay(MomoService $momo){
     $response=$momo->requestToPay('0769010507','5000');
     return $response->getBody();
    }

}
