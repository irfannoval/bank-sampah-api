<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Services\PenarikanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private PenarikanService $penarikan)
    {
    }

    /**
     * Menerima hasil disbursement. Tanda tangan HMAC SHA-256 dari isi mentah permintaan
     * dikirim di header X-Signature. CATATAN: sesuaikan nama header dan format isi
     * dengan provider gateway yang dipilih.
     */
    public function disbursement(Request $request): JsonResponse
    {
        $rahasia = (string) config('gateway.webhook_secret');
        if ($rahasia === '') {
            throw new ApiException('WEBHOOK_BELUM_DIATUR', 'Rahasia webhook belum diatur.', 503);
        }

        $tandaTangan = hash_hmac('sha256', $request->getContent(), $rahasia);
        if (!hash_equals($tandaTangan, (string) $request->header('X-Signature'))) {
            throw new ApiException('TANDA_TANGAN_SALAH', 'Tanda tangan tidak valid.', 401);
        }

        $data = $request->validate([
            'ref' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:berhasil,gagal'],
        ]);

        $ditemukan = $this->penarikan->selesaikan($data['ref'], $data['status']);

        return response()->json(['ok' => $ditemukan], $ditemukan ? 200 : 404);
    }
}
