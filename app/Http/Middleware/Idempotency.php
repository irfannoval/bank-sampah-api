<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Wajib dipasang di POST yang mengubah uang.
 * Kunci yang sama + isi permintaan yang sama = hasil lama dikembalikan, tidak diproses ulang.
 * Hanya respons sukses (2xx) yang disimpan; kegagalan boleh dicoba lagi.
 */
class Idempotency
{
    public function handle(Request $request, Closure $next)
    {
        $kunci = $request->header('Idempotency-Key');
        if (!$kunci || strlen($kunci) > 100) {
            throw new ApiException('IDEMPOTENCY_KEY_WAJIB', 'Header Idempotency-Key wajib diisi (maks 100 karakter).', 400);
        }

        $userId = $request->user()->id;
        $sidik = hash('sha256', $request->method() . $request->path() . $request->getContent());
        $kunciRow = ['user_id' => $userId, 'kunci' => $kunci];

        $ada = DB::table('idempotency_keys')->where($kunciRow)->first();
        if ($ada) {
            return $this->ulang($ada, $sidik);
        }

        try {
            DB::table('idempotency_keys')->insert($kunciRow + ['sidik' => $sidik, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // Permintaan kembar yang bersamaan: yang kalah membaca baris pemenang.
            return $this->ulang(DB::table('idempotency_keys')->where($kunciRow)->first(), $sidik);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            DB::table('idempotency_keys')->where($kunciRow)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        } else {
            DB::table('idempotency_keys')->where($kunciRow)->delete();
        }

        return $response;
    }

    private function ulang(object $row, string $sidik)
    {
        if ($row->sidik !== $sidik) {
            throw new ApiException('DUPLIKAT', 'Idempotency-Key ini sudah dipakai untuk permintaan yang berbeda.', 422);
        }
        if ($row->response_status === null) {
            throw new ApiException('SEDANG_DIPROSES', 'Permintaan yang sama masih diproses.', 409);
        }

        return response($row->response_body, $row->response_status)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
