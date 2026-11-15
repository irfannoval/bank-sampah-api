<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AkunService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MitraController extends Controller
{
    public function __construct(private AkunService $akun, private AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            DB::table('toko_mitra')->join('users', 'users.id', '=', 'toko_mitra.user_id')
                ->select('toko_mitra.id', 'toko_mitra.nama_toko', 'toko_mitra.alamat', 'toko_mitra.saldo_tagihan',
                    'toko_mitra.bank_tujuan', 'toko_mitra.no_rekening', 'toko_mitra.atas_nama', 'users.nama', 'users.no_hp', 'users.status')
                ->orderBy('toko_mitra.nama_toko')->paginate($this->perPage($request))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'no_hp' => ['required', 'string', 'max:20', 'unique:users,no_hp'],
            'nama_toko' => ['required', 'string', 'max:150'],
            'alamat' => ['nullable', 'string'],
            'bank_tujuan' => ['nullable', 'string', 'max:60'],
            'no_rekening' => ['nullable', 'string', 'max:40'],
            'atas_nama' => ['nullable', 'string', 'max:120'],
            'pin' => ['nullable', 'digits:6'],
        ]);

        return response()->json($this->akun->daftarMitra($data, $request->user()->id), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $m = DB::table('toko_mitra')->where('id', $id)->first();
        if (!$m) {
            throw new ApiException('MITRA_TIDAK_DITEMUKAN', 'Toko mitra tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'nama_toko' => ['sometimes', 'string', 'max:150'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'bank_tujuan' => ['sometimes', 'nullable', 'string', 'max:60'],
            'no_rekening' => ['sometimes', 'nullable', 'string', 'max:40'],
            'atas_nama' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:aktif,nonaktif'],
        ]);

        $log = $data;
        DB::transaction(function () use ($data, $m, $request, $log) {
            if (isset($data['status'])) {
                DB::table('users')->where('id', $m->user_id)->update(['status' => $data['status']]);
                unset($data['status']);
            }
            if ($data) {
                DB::table('toko_mitra')->where('id', $m->id)->update($data);
            }
            $this->audit->catat($request->user()->id, 'mitra.ubah', 'toko_mitra', null, ['toko_mitra_id' => $m->id] + $log);
        });

        return response()->json(['ok' => true]);
    }

    public function resetPin(Request $request, int $id): JsonResponse
    {
        $m = DB::table('toko_mitra')->where('id', $id)->first();
        if (!$m) {
            throw new ApiException('MITRA_TIDAK_DITEMUKAN', 'Toko mitra tidak ditemukan.', 404);
        }

        return response()->json($this->akun->resetPin($m->user_id, $request->user()->id));
    }
}
