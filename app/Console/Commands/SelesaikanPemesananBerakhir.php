<?php

namespace App\Console\Commands;

use App\Enums\StatusPemesanan;
use App\Exceptions\PemesananException;
use App\Models\Pemesanan;
use App\Services\PemesananService;
use Illuminate\Console\Command;

/**
 * Menutup pesanan yang masa sewanya sudah lewat dan mengembalikan kamarnya
 * ke stok.
 *
 * Setelah berstatus selesai, penyewa baru dapat menulis ulasan — lihat
 * Pemesanan::bolehDiulas().
 */
class SelesaikanPemesananBerakhir extends Command
{
    protected $signature = 'pemesanan:selesaikan';

    protected $description = 'Menutup pesanan yang masa sewanya sudah berakhir dan mengembalikan kamarnya';

    public function handle(PemesananService $service): int
    {
        $berakhir = Pemesanan::query()
            ->where('status', StatusPemesanan::Aktif)
            ->whereDate('tanggal_selesai', '<', today())
            ->get();

        $berhasil = 0;

        foreach ($berakhir as $pemesanan) {
            try {
                $service->selesaikan($pemesanan);
                $berhasil++;
            } catch (PemesananException $e) {
                $this->warn("Pesanan #{$pemesanan->id} dilewati: {$e->getMessage()}");
            }
        }

        $this->info("{$berhasil} pesanan ditutup karena masa sewanya berakhir.");

        return self::SUCCESS;
    }
}
