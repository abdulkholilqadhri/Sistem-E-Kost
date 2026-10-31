<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak permintaan dari akun yang dinonaktifkan admin (FR-16).
 *
 * Pemeriksaan ini dilakukan pada setiap permintaan, bukan hanya saat login.
 * Tanpa itu, pengguna yang sudah terlanjur login tetap dapat memakai sistem
 * sampai sesinya kedaluwarsa, sehingga penonaktifan akun tidak berefek apa pun
 * pada orang yang sedang aktif.
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isAktif()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'Akun Anda dinonaktifkan. Hubungi admin untuk informasi lebih lanjut.');
        }

        return $next($request);
    }
}
