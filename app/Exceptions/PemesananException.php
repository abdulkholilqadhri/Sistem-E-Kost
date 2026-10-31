<?php

namespace App\Exceptions;

use App\Enums\StatusPemesanan;
use RuntimeException;

/**
 * Kesalahan aturan bisnis pemesanan.
 *
 * Dibedakan dari kesalahan validasi biasa karena penyebabnya bukan bentuk
 * masukan yang salah, melainkan keadaan sistem pada saat itu: kamar yang
 * habis didahului orang lain, atau perpindahan status yang tidak sah.
 * Keduanya dapat terjadi meskipun seluruh masukan pengguna benar.
 */
class PemesananException extends RuntimeException
{
    public static function kamarHabis(): self
    {
        return new self(
            'Kamar sudah habis. Pesanan ini tidak dapat diterima karena '
            .'seluruh kamar telah terisi lebih dulu.',
        );
    }

    public static function transisiTidakSah(StatusPemesanan $dari, StatusPemesanan $ke): self
    {
        return new self(
            "Pesanan berstatus \"{$dari->label()}\" tidak dapat diubah menjadi \"{$ke->label()}\".",
        );
    }

    public static function kosTidakTersedia(): self
    {
        return new self('Kos ini sedang tidak menerima pemesanan.');
    }

    public static function sudahPunyaPesananAktif(): self
    {
        return new self('Anda masih memiliki pesanan yang berjalan untuk kos ini.');
    }

    public static function belumWaktunyaMembayar(): self
    {
        return new self('Bukti pembayaran hanya dapat diunggah setelah pemilik menerima pesanan.');
    }
}
