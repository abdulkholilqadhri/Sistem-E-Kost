<?php

namespace App\Console\Commands;

use App\Enums\StatusPemesanan;
use App\Exceptions\PemesananException;
use App\Models\Pemesanan;
use App\Services\PemesananService;
use Illuminate\Console\Command;

/**
 * Membatalkan pesanan yang melewati tenggat pembayaran dan mengembalikan
 * kamarnya ke stok.
 *
 * Tanpa perintah ini, kolom batas_pembayaran dan hitung mundur pada antarmuka
 * tidak berarti apa-apa: pesanan yang tidak pernah dibayar akan menahan kamar
 * selamanya, dan sisa_kamar perlahan menyimpang dari keadaan sebenarnya.
 */
class BatalkanPemesananKedaluwarsa extends Command
{
    protected $signature = 'pemesanan:kedaluwarsa';

    protected $description = 'Membatalkan pesanan yang melewati tenggat pembayaran dan mengembalikan kamarnya';

    public function handle(PemesananService $service): int
    {
        $kedaluwarsa = Pemesanan::query()
            ->where('status', StatusPemesanan::MenungguPembayaran)
            ->whereNotNull('batas_pembayaran')
            ->where('batas_pembayaran', '<', now())
            ->get();

        $berhasil = 0;

        foreach ($kedaluwarsa as $pemesanan) {
            try {
                $service->batalkan($pemesanan, 'Dibatalkan otomatis: batas waktu pembayaran terlampaui.');
                $berhasil++;
            } catch (PemesananException $e) {
                // Status berubah di antara pengambilan data dan pemrosesan —
                // misalnya penyewa baru saja mengunggah bukti. Dilewati, bukan
                // dipaksakan.
                $this->warn("Pesanan #{$pemesanan->id} dilewati: {$e->getMessage()}");
            }
        }

        $this->info("{$berhasil} pesanan dibatalkan karena melewati tenggat pembayaran.");

        return self::SUCCESS;
    }
}
