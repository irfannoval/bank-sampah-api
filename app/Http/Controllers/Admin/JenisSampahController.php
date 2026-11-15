<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JenisSampahController extends Controller
{
    public function __construct(private AuditService $audit)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(DB::table('jenis_sampah')->orderBy('nama')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', 'unique:jenis_sampah,nama'],
            'kategori' => ['nullable', 'string', 'max:60'],
            'harga_per_kg' => ['required', 'regex:' . self::REGEX_NOMINAL],
        ]);
        $id = DB::table('jenis_sampah')->insertGetId($data);
        $this->audit->catat($request->user()->id, 'jenis_sampah.tambah', 'jenis_sampah', null, ['id' => $id] + $data);

        return response()->json(['id' => $id], 201);
    }

    /** Mengubah harga TIDAK mengubah setoran lama (harga disimpan per detail_setoran). */
    public function update(Request $request, int $id): JsonResponse
    {
        $lama = DB::table('jenis_sampah')->where('id', $id)->first();
        if (!$lama) {
            throw new ApiException('JENIS_SAMPAH_TIDAK_VALID', 'Jenis sampah tidak ditemukan.', 404);
        }

        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:100', 'unique:jenis_sampah,nama,' . $id],
            'kategori' => ['sometimes', 'nullable', 'string', 'max:60'],
            'harga_per_kg' => ['sometimes', 'regex:' . self::REGEX_NOMINAL],
            'aktif' => ['sometimes', 'boolean'],
        ]);
        DB::table('jenis_sampah')->where('id', $id)->update($data);
        $this->audit->catat($request->user()->id, 'jenis_sampah.ubah', 'jenis_sampah', (array) $lama, $data);

        return response()->json(['ok' => true]);
    }
}
