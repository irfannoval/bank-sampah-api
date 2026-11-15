<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Pengaturan, audit log, dan pengecekan selisih saldo (admin_utama). */
class SistemController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function pengaturan(): JsonResponse
    {
        return response()->json(DB::table('pengaturan_sistem')->orderBy('kunci')->get());
    }

    public function ubahPengaturan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pengaturan' => ['required', 'array', 'min:1'],
            'pengaturan.*' => ['required', 'regex:/^\d+(\.\d+)?$/'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['pengaturan'] as $kunci => $nilai) {
                $lama = DB::table('pengaturan_sistem')->where('kunci', $kunci)->first();
                if (!$lama) {
                    throw new ApiException('PENGATURAN_TIDAK_DITEMUKAN', "Pengaturan '{$kunci}' tidak ada.", 404);
                }
                DB::table('pengaturan_sistem')->where('kunci', $kunci)->update([
                    'nilai' => (string) $nilai, 'diubah_oleh' => $request->user()->id, 'updated_at' => now(),
                ]);
                $this->audit->catat($request->user()->id, 'pengaturan.ubah', 'pengaturan_sistem', ['kunci' => $kunci, 'nilai' => $lama->nilai], ['nilai' => (string) $nilai]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function auditLog(Request $request): JsonResponse
    {
        return response()->json(DB::table('audit_log')->orderByDesc('id')->paginate($this->perPage($request)));
    }

    /** Seharusnya selalu kosong. Jika ada isi, saldo dan ledger tidak cocok: selidiki segera. */
    public function selisihSaldo(): JsonResponse
    {
        return response()->json(DB::table('v_selisih_saldo')->get());
    }
}
