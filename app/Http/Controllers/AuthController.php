<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private PinService $pin)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'no_hp' => ['required', 'string', 'max:20'],
            'pin' => ['required', 'digits:6'],
        ]);

        $user = User::where('no_hp', $data['no_hp'])->first();

        // Pesan sama untuk akun tidak ada / nonaktif / PIN salah (cegah penebakan nomor).
        if (!$user || $user->status !== 'aktif') {
            throw new ApiException('PIN_SALAH', 'No HP atau PIN salah.');
        }

        $this->pin->verifikasi($user, $data['pin']);

        $token = $user->createToken('api', [$user->role])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'nama', 'no_hp', 'role']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function saya(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->only(['id', 'nama', 'no_hp', 'role', 'status'])]);
    }

    public function ubahPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin_lama' => ['required', 'digits:6'],
            'pin_baru' => ['required', 'digits:6', 'different:pin_lama'],
        ]);

        $this->pin->verifikasi($request->user(), $data['pin_lama']);
        $request->user()->forceFill(['pin_hash' => Hash::make($data['pin_baru'])])->save();

        return response()->json(['ok' => true]);
    }
}
