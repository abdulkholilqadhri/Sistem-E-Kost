<?php

use App\Http\Controllers\Api\Admin\VerifikasiPengajuanController;
use App\Http\Controllers\Api\FotoKosController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\KosController;
use App\Http\Controllers\Api\PembayaranController;
use App\Http\Controllers\Api\PemesananController;
use App\Http\Controllers\Api\PengajuanPemilikController;
use App\Http\Controllers\Api\RuteController;
use App\Http\Controllers\Api\UlasanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute API E-Kost
|--------------------------------------------------------------------------
|
| Berkas ini berisi endpoint data. Rute autentikasi (registrasi, login,
| logout, saya) ada di routes/web.php — penjelasannya ada di sana.
|
| Autentikasi memakai cookie sesi yang terbentuk saat login, bukan token.
| Frontend wajib memanggil GET /sanctum/csrf-cookie sebelum permintaan POST
| pertama, dan mengirim seluruh permintaan dengan withCredentials.
|
| Susunan middleware yang dipakai di berkas ini:
|   auth:sanctum  -> harus sudah masuk
|   akun.aktif    -> akun belum dinonaktifkan admin (FR-16)
|   role:...      -> peran yang diizinkan
|
| Middleware role hanya menjawab "peran apa". Pemeriksaan "data milik siapa"
| ada pada Policy, dipanggil dari masing-masing controller.
|
*/

/*
|--------------------------------------------------------------------------
| Publik — tanpa login
|--------------------------------------------------------------------------
|
| Calon penyewa harus dapat melihat peta dan mencari kos sebelum mendaftar.
| Memaksa login lebih dulu akan membuat sebagian besar pengunjung pergi.
|
| Urutan di bawah penting: /kos/cari harus didaftarkan sebelum /kos/{kos},
| karena bila terbalik, Laravel akan memperlakukan kata "cari" sebagai id kos.
|
*/

Route::get('/kos', [KosController::class, 'index'])->name('kos.index');
Route::get('/kos/cari', [KosController::class, 'cari'])->name('kos.cari');
Route::get('/kos/{kos}', [KosController::class, 'show'])->name('kos.show');

/*
 * FR-06 — Rute ke kos. Dibatasi 30 permintaan per menit per alamat IP karena
 * setiap panggilan yang tidak tertangkap cache diteruskan ke OSRM publik,
 * yang punya kebijakan pemakaian.
 */
Route::get('/kos/{kos}/rute', RuteController::class)
    ->middleware('throttle:30,1')
    ->name('kos.rute');

/*
 * FR-09 — Ulasan. Dapat dibaca siapa saja; penulisannya butuh login dan
 * pemesanan yang sudah selesai (lihat grup di bawah).
 */
Route::get('/kos/{kos}/ulasan', [UlasanController::class, 'index'])->name('kos.ulasan');
Route::get('/ulasan/terbaru', [UlasanController::class, 'terbaru'])->name('ulasan.terbaru');

Route::middleware(['auth:sanctum', 'akun.aktif'])->group(function () {

    /*
     * FR-14 — Pengajuan menjadi pemilik kos, sisi pemohon.
     */
    Route::get('/pengajuan-pemilik', [PengajuanPemilikController::class, 'index'])
        ->name('pengajuan-pemilik.index');
    Route::post('/pengajuan-pemilik', [PengajuanPemilikController::class, 'store'])
        ->name('pengajuan-pemilik.store');
    Route::get('/pengajuan-pemilik/{pengajuan}', [PengajuanPemilikController::class, 'show'])
        ->name('pengajuan-pemilik.show');

    /*
     * FR-08 — Pemesanan, sisi penyewa.
     */
    Route::get('/pemesanan', [PemesananController::class, 'index'])->name('pemesanan.index');
    Route::post('/pemesanan', [PemesananController::class, 'store'])->name('pemesanan.store');
    Route::get('/pemesanan/{pemesanan}', [PemesananController::class, 'show'])->name('pemesanan.show');
    Route::post('/pemesanan/{pemesanan}/batal', [PemesananController::class, 'batal'])->name('pemesanan.batal');

    /*
     * FR-12 — Unggah bukti pembayaran, sisi penyewa.
     */
    Route::post('/pemesanan/{pemesanan}/pembayaran', [PembayaranController::class, 'store'])
        ->name('pembayaran.store');

    /*
     * FR-09 — Menulis ulasan. Hanya penyewa pemilik pesanan, hanya untuk
     * pesanan yang sudah selesai, dan hanya sekali per pesanan.
     */
    Route::post('/pemesanan/{pemesanan}/ulasan', [UlasanController::class, 'store'])
        ->name('ulasan.store');

    Route::delete('/ulasan/{ulasan}', [UlasanController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('ulasan.destroy');

    /*
     * Berkas bukti transfer. Diizinkan untuk penyewa pemilik pesanan, pemilik
     * kos yang bersangkutan, dan admin — ditentukan PembayaranPolicy.
     */
    Route::get('/pembayaran/{pembayaran}/bukti', [PembayaranController::class, 'bukti'])
        ->name('pembayaran.bukti');

    /*
     * Pengelolaan kos oleh pemilik.
     */
    Route::middleware('role:pemilik,admin')->group(function () {
        Route::get('/kos-saya', [KosController::class, 'milikSaya'])->name('kos.milik-saya');
        Route::post('/kos', [KosController::class, 'store'])->name('kos.store');
        Route::put('/kos/{kos}', [KosController::class, 'update'])->name('kos.update');
        Route::delete('/kos/{kos}', [KosController::class, 'destroy'])->name('kos.destroy');

        /*
         * Pencarian alamat untuk menaruh titik kos di peta.
         *
         * Dibatasi 20 permintaan per menit. Nominatim membatasi satu
         * permintaan per detik dan memblokir alamat IP yang melampauinya —
         * yang terblokir adalah server aplikasi, bukan satu pengguna.
         */
        Route::get('/geocode', GeocodingController::class)
            ->middleware('throttle:20,1')
            ->name('geocode');

        Route::post('/kos/{kos}/foto', [FotoKosController::class, 'store'])->name('kos.foto.store');
        Route::delete('/kos/{kos}/foto/{foto}', [FotoKosController::class, 'destroy'])->name('kos.foto.destroy');

        /*
         * FR-11 — Konfirmasi pesanan masuk.
         * FR-13 — Verifikasi bukti pembayaran.
         */
        Route::get('/pemesanan-masuk', [PemesananController::class, 'masuk'])->name('pemesanan.masuk');
        Route::post('/pemesanan/{pemesanan}/keputusan', [PemesananController::class, 'keputusan'])
            ->name('pemesanan.keputusan');
        Route::post('/pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])
            ->name('pembayaran.verifikasi');
    });

    /*
     * FR-15 — Verifikasi pengajuan, sisi admin.
     */
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengajuan-pemilik', [VerifikasiPengajuanController::class, 'index'])
            ->name('pengajuan-pemilik.index');
        Route::get('/pengajuan-pemilik/{pengajuan}', [VerifikasiPengajuanController::class, 'show'])
            ->name('pengajuan-pemilik.show');
        Route::get('/pengajuan-pemilik/{pengajuan}/dokumen/{jenis}', [VerifikasiPengajuanController::class, 'dokumen'])
            ->whereIn('jenis', ['ktp', 'kepemilikan'])
            ->name('pengajuan-pemilik.dokumen');
        Route::post('/pengajuan-pemilik/{pengajuan}/verifikasi', [VerifikasiPengajuanController::class, 'verifikasi'])
            ->name('pengajuan-pemilik.verifikasi');
    });

});
