<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PencairanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('pencairan_mitra')
            ->join('toko_mitra', 'toko_mitra.id', '=', 'pencairan_mitra.toko_mitra_id')
            ->select('pencairan_mitra.*', 'toko_mitra.nama_toko')
            ->orderByDesc('pencairan_mitra.id');

        if ($status = $request->query('status')) {
            $query->where('pencairan_mitra.status', $status);
        }

        return response()->json($query->paginate($this->perPage($request)));
    }
}
