<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tugas Terjadwal E-Kost
|--------------------------------------------------------------------------
|
| Keduanya menjaga agar kos.sisa_kamar tetap mencerminkan keadaan sebenarnya.
| Tanpa keduanya, kamar yang ditahan pesanan tak terbayar atau sewa yang sudah
| lewat tidak akan pernah kembali ke stok.
|
| Agar benar-benar berjalan, penjadwal Laravel harus dipanggil tiap menit oleh
| cron pada server:
|
|   * * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
|
| Di lingkungan pengembangan, "php artisan schedule:work" menjalankan hal yang
| sama pada terminal selama dibiarkan terbuka.
|
*/

Schedule::command('pemesanan:kedaluwarsa')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('pemesanan:selesaikan')
    ->dailyAt('00:30')
    ->withoutOverlapping();
