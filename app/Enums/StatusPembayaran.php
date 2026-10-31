<?php

namespace App\Enums;

/**
 * Status verifikasi bukti pembayaran (FR-12, FR-13).
 *
 * Transisi sah:
 *   Menunggu -> Terverifikasi  pemilik menyetujui; pemesanan menjadi Aktif
 *   Menunggu -> Ditolak        pemilik menolak; pemesanan kembali ke MenungguPembayaran
 *
 * SDD memakai tiga istilah berbeda untuk hal yang sama (Diproses/valid/gagal
 * pada tabel, menunggu/terverifikasi/ditolak pada flowchart, lunas pada
 * sequence diagram). Enum ini yang dipakai.
 */
enum StatusPembayaran: string
{
    case Menunggu = 'menunggu';
    case Terverifikasi = 'terverifikasi';
    case Ditolak = 'ditolak';

    public function final(): bool
    {
        return $this === self::Terverifikasi;
    }

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Verifikasi',
            self::Terverifikasi => 'Terverifikasi',
            self::Ditolak => 'Ditolak',
        };
    }
}
