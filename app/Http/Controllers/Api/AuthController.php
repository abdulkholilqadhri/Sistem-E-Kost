<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUser;
use App\Enums\StatusAkun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegistrasiRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autentikasi SPA dengan cookie sesi (Sanctum stateful), bukan token.
 *
 * Alur yang harus diikuti frontend:
 *   1. GET  /sanctum/csrf-cookie   -> menanam cookie XSRF-TOKEN
 *   2. POST /api/registrasi | /api/login
 *   3. GET  /api/saya              -> memeriksa sesi yang sedang berjalan
 *
 * Token tidak disimpan di localStorage karena satu celah XSS akan cukup untuk
 * mencurinya. Cookie sesi bersifat HttpOnly sehingga tidak terbaca JavaScript.
 */
class AuthController extends Controller
{
    public function registrasi(RegistrasiRequest $request): JsonResponse
    {
        // Peran tidak diambil dari permintaan, melainkan ditetapkan di sini.
        //
        // Keduanya memang punya nilai bawaan di tingkat kolom basis data,
        // tetapi User::create() tidak membaca ulang baris yang baru disimpan,
        // sehingga objek di memori tidak akan memiliki nilai itu dan respons
        // JSON-nya gagal. Penetapan eksplisit sekaligus membuat maksudnya
        // terbaca: peran ditentukan server, bukan oleh isi permintaan.
        $user = new User($request->validated());
        $user->role = RoleUser::Penyewa;
        $user->status_akun = StatusAkun::Aktif;
        $user->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'pesan' => 'Pendaftaran berhasil.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return response()->json([
            'pesan' => 'Berhasil masuk.',
            'data' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['pesan' => 'Berhasil keluar.']);
    }

    public function saya(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
