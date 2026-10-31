<?php

namespace App\Enums;

/**
 * State machine status pemesanan.
 *
 * Enum ini menggantikan tiga penamaan status yang saling bertabrakan di SDD
 * (ENUM tabel memakai menunggu/aktif/selesai, sequence diagram memakai
 * diterima/ditolak, activity diagram memakai istilah lain lagi). Mulai dari
 * sini, enum ini adalah satu-satunya sumber kebenaran status pemesanan.
 *
 * Transisi sah dan pengaruhnya terhadap kos.sisa_kamar:
 *
 *   MenungguKonfirmasi -> MenungguPembayaran  pemilik menerima; sisa_kamar -1
 *   MenungguKonfirmasi -> Ditolak             pemilik menolak;  sisa_kamar tetap
 *   MenungguKonfirmasi -> Dibatalkan          penyewa batal;    sisa_kamar tetap
 *   MenungguPembayaran -> MenungguVerifikasi  penyewa unggah bukti
 *   MenungguPembayaran -> Dibatalkan          batas waktu lewat; sisa_kamar +1
 *   MenungguVerifikasi -> Aktif               pemilik verifikasi bukti
 *   MenungguVerifikasi -> MenungguPembayaran  bukti ditolak, penyewa unggah ulang
 *   Aktif              -> Selesai             masa sewa berakhir; sisa_kamar +1
 *
 * Catatan desain: kamar baru dikurangi saat pemilik menerima pesanan, bukan
 * saat pesanan diajukan. Beberapa pengajuan boleh antre untuk kamar yang sama;
 * pemilik yang memutuskan. Pengurangan dan pengembalian wajib dijalankan di
 * dalam transaksi dengan lockForUpdate pada baris kos agar dua penyewa tidak
 * dapat mengambil kamar terakhir secara bersamaan.
 */
enum StatusPemesanan: string
{
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case MenungguPembayaran = 'menunggu_pembayaran';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    /**
     * Daftar status tujuan yang sah dari status saat ini.
     *
     * @return array<int, self>
     */
    public function transisiSah(): array
    {
        return match ($this) {
            self::MenungguKonfirmasi => [self::MenungguPembayaran, self::Ditolak, self::Dibatalkan],
            self::MenungguPembayaran => [self::MenungguVerifikasi, self::Dibatalkan],
            self::MenungguVerifikasi => [self::Aktif, self::MenungguPembayaran],
            self::Aktif => [self::Selesai],
            self::Selesai, self::Ditolak, self::Dibatalkan => [],
        };
    }

    public function bolehPindahKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->transisiSah(), strict: true);
    }

    /**
     * Status final tidak dapat diubah lagi oleh siapa pun.
     */
    public function final(): bool
    {
        return $this->transisiSah() === [];
    }

    /**
     * Apakah pemesanan pada status ini sedang menahan satu kamar.
     */
    public function menahanKamar(): bool
    {
        return in_array($this, [
            self::MenungguPembayaran,
            self::MenungguVerifikasi,
            self::Aktif,
        ], strict: true);
    }

    public function label(): string
    {
        return match ($this) {
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi Pemilik',
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::MenungguVerifikasi => 'Menunggu Verifikasi Pembayaran',
            self::Aktif => 'Aktif',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
