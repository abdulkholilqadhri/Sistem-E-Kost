<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute berdasarkan peran pengguna.
 *
 * Middleware ini hanya menjawab "peran apa", bukan "data milik siapa".
 * Pemilik kos A dan pemilik kos B sama-sama lolos middleware 'role:pemilik';
 * yang mencegah A menyentuh data B adalah Policy pada masing-masing model.
 * Keduanya harus dipakai bersama.
 *
 * Contoh: ->middleware(['auth:sanctum', 'akun.aktif', 'role:pemilik,admin'])
 */
class PastikanRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! in_array($user->role->value, $roles, strict: true)) {
            abort(403, 'Anda tidak memiliki akses ke bagian ini.');
        }

        return $next($request);
    }
}
