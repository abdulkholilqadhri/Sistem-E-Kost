<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Web
|--------------------------------------------------------------------------
|
| Rute autentikasi sengaja berada di berkas ini, bukan di routes/api.php,
| meskipun alamatnya berawalan /api.
|
| Alasannya: autentikasi SPA memakai cookie sesi. Grup middleware "web"
| selalu memasang StartSession dan ValidateCsrfToken, sementara grup "api"
| hanya memasangnya lewat EnsureFrontendRequestsAreStateful, yang baru aktif
| bila header Origin atau Referer cocok dengan SANCTUM_STATEFUL_DOMAINS.
| Permintaan tanpa header itu — termasuk seluruh permintaan dari test —
| tidak mendapat sesi, dan $request->session() melempar RuntimeException.
|
| Menaruh registrasi, login, dan logout di sini membuat sesi selalu tersedia,
| tidak bergantung pada header yang dikirim klien. Ini juga pola baku Laravel
| Sanctum untuk SPA satu domain.
|
| routes/api.php tetap dipakai untuk endpoint data, yang diautentikasi dengan
| cookie sesi yang sudah terbentuk di sini.
|
*/

Route::prefix('api')->group(function () {
    Route::post('/registrasi', [AuthController::class, 'registrasi'])->name('registrasi');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware(['auth:sanctum', 'akun.aktif'])->group(function () {
        Route::get('/saya', [AuthController::class, 'saya'])->name('saya');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

/*
|--------------------------------------------------------------------------
| Cangkang SPA
|--------------------------------------------------------------------------
|
| Seluruh alamat selain /api dan /sanctum dilayani satu halaman yang sama.
| Vue Router yang menentukan tampilan berdasarkan alamatnya.
|
| Rute ini WAJIB berada paling bawah: pola .* menangkap apa pun, sehingga
| rute apa pun yang didaftarkan sesudahnya tidak akan pernah tercapai.
|
| Pengecualian api dan sanctum penting karena permintaan yang salah alamat ke
| /api akan dijawab dengan HTML halaman ini, dan pesan galat di peramban akan
| berbunyi "Unexpected token <" — petunjuk yang menyesatkan.
|
*/

/**
 * Konfigurasi yang ditanam ke halaman untuk dibaca Vue.
 *
 * Disusun di sini, bukan sebagai argumen directive @json di dalam Blade:
 * Blade mem-parsing argumen directive dengan pencocokan tanda kurung yang
 * tidak tahan terhadap array bersarang yang dipecah ke banyak baris, dan
 * kegagalannya muncul sebagai "Unclosed '[' does not match ')'" yang
 * menunjuk ke baris yang salah.
 */
$cangkangSpa = fn () => view('app', [
    'ekost' => [
        'kampus' => [
            'nama' => config('ekost.kampus.nama'),
            'lat' => config('ekost.kampus.lat'),
            'lng' => config('ekost.kampus.lng'),
        ],
        'radius_default' => config('ekost.radius_default'),
        'radius_maksimum' => config('ekost.radius_maksimum'),

        // Ditanam ke halaman agar formulir pemilik dan penyaring pencarian
        // memakai ejaan yang sama persis. Penjelasannya di config/ekost.php.
        'fasilitas' => config('ekost.fasilitas'),
    ],
]);

Route::get('/', $cangkangSpa)->name('beranda');

Route::get('/{halaman}', $cangkangSpa)
    ->where('halaman', '^(?!api|sanctum|storage|up).*$')
    ->name('spa');
