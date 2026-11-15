<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\PencairanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PencairanController extends Controller
{
    public function __construct(private PencairanService $pencairan)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('pencairan_mitra')->join('toko_mitra', 'toko_mitra.id', '=', 'pencairan_mitra.toko_mitra_id')
            ->select('pencairan_mitra.*', 'toko_mitra.nama_toko', 'toko_mitra.bank_tujuan', 'toko_mitra.no_rekening', 'toko_mitra.atas_nama')
            ->orderByDesc('pencairan_mitra.id');
        if ($s = $request->query('status')) {
            $q->where('pencairan_mitra.status', $s);
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    public function setujui(Request $request, int $id): JsonResponse
    {
        $this->pencairan->setujui($id, $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function tolak(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']]);
        $this->pencairan->tolak($id, $request->user()->id, $data['alasan']);

        return response()->json(['ok' => true]);
    }

    public function bayar(Request $request, int $id): JsonResponse
    {
        $request->validate(['bukti_transfer' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096']]);
        $this->pencairan->bayar($id, $request->user()->id, $request->file('bukti_transfer'));

        return response()->json(['ok' => true]);
    }

    public function bukti(int $id): StreamedResponse
    {
        $path = DB::table('pencairan_mitra')->where('id', $id)->value('bukti_transfer');
        if (!$path || !Storage::exists($path)) {
            throw new ApiException('BUKTI_TIDAK_ADA', 'Bukti transfer tidak ditemukan.', 404);
        }

        return Storage::download($path);
    }
}
