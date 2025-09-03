<?php

namespace App\Http\Controllers;

use App\Services\MomoService;

class PaymentController extends Controller
{
    public function requestToPay(MomoService $momo)
    {
        $response = $momo->requestToPay('0769010507', 5000);

        return $response;
    }

    public function status(MomoService $momo, $referenceId)
    {
        $status = $momo->getPaymentStatus($referenceId);

        return response()->json($status);
    }
}
