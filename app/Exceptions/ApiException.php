<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Error bisnis dengan format seragam:
 * {"error": {"kode": "SALDO_KURANG", "pesan": "Saldo tidak cukup"}}
 */
class ApiException extends Exception
{
    public function __construct(
        public string $kode,
        string $pesan,
        public int $status = 422,
        public array $extra = [],
    ) {
        parent::__construct($pesan);
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'error' => array_merge(['kode' => $this->kode, 'pesan' => $this->getMessage()], $this->extra),
        ], $this->status);
    }
}
