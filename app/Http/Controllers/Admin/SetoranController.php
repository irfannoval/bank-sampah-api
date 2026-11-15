<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SetoranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetoranController extends Controller
{
    public function __construct(private SetoranService $setoran)
    {
    }

    private const ATURAN_ITEMS = [
        'items' => ['required', 'array', 'min:1', 'max:50'],
        'items.*.jenis_sampah_id' => ['required', 'integer'],
        'items.*.berat' => ['required', 'regex:/^\d{1,9}(\.\d{1,3})?$/'],
    ];

    public function index(Request $request): JsonResponse
    {
        $q = DB::table('setoran')->join('nasabah', 'nasabah.id', '=', 'setoran.nasabah_id')
            ->join('users', 'users.id', '=', 'nasabah.user_id')
            ->select('setoran.id', 'setoran.total_berat', 'setoran.total_nilai', 'setoran.status', 'setoran.created_at',
                'nasabah.kode_nasabah', 'users.nama')
            ->orderByDesc('setoran.id');
        if ($d = $request->query('dari')) {
            $q->where('setoran.created_at', '>=', $d);
        }
        if ($s = $request->query('sampai')) {
            $q->where('setoran.created_at', '<', date('Y-m-d', strtotime($s . ' +1 day')));
        }

        return response()->json($q->paginate($this->perPage($request)));
    }

    /** Pratinjau: total berat, total nilai, saldo yang akan diterima nasabah. */
    public function hitung(Request $request): JsonResponse
    {
        $data = $request->validate(self::ATURAN_ITEMS);

        return response()->json($this->setoran->hitung($data['items']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(self::ATURAN_ITEMS + [
            'nasabah_id' => ['required', 'integer'],
            'kartu_id' => ['nullable', 'integer'],
        ]);

        $hasil = $this->setoran->simpan(
            $request->user()->id,
            (int) $data['nasabah_id'],
            isset($data['kartu_id']) ? (int) $data['kartu_id'] : null,
            $data['items'],
        );

        return response()->json($hasil, 201);
    }

    /** Hanya admin_utama (dibatasi di rute). */
    public function batal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']]);

        return response()->json($this->setoran->batal($id, $request->user()->id, $data['alasan']));
    }
}
