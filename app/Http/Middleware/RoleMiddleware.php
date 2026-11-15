<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;

/** Pemakaian: RoleMiddleware::class . ':admin,admin_utama' */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user || $user->status !== 'aktif' || !in_array($user->role, $roles, true)) {
            throw new ApiException('TIDAK_BERWENANG', 'Anda tidak berwenang untuk aksi ini.', 403);
        }

        return $next($request);
    }
}
