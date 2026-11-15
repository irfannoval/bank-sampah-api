<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MitraTransaksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiMitraController extends Controller
{
    public function __construct(private MitraTransaksiService $transaksi)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('transaksi_mitra')->join('toko_mitra', 'toko_mitra.id', '=', 'transaksi_mitra.toko_mitra_id')
            ->join('nasabah', 'nasabah.id', '=', 'transaksi_mitra.nasabah_id')
            ->join('users', 'users.id', '=', 'nasabah.user_id')
            ->select('transaksi_mitra.id', 'transaksi_mitra.nominal', 'transaksi_mitra.status', 'transaksi_mitra.pencairan_id',
                'transaksi_mitra.created_at', 'toko_mitra.nama_toko', 'nasabah.kode_nasabah', 'users.nama')
            ->orderByDesc('transaksi_mitra.id');
        if ($m = $request->query('toko_mitra_id')) {
            $q->where('transaksi_mitra.toko_mitra_id', $m);
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    public function batal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json($this->transaksi->batal($request->user(), $id, $data['alasan']));
    }
}
