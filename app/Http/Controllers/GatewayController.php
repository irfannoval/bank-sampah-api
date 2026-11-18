<?php

namespace App\Http\Controllers;

use App\Services\Gateway\GatewayInterface;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{
    public function banks(GatewayInterface $gateway): JsonResponse
    {
        return response()->json($gateway->daftarBank());
    }
}
