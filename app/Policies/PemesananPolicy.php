<?php

namespace App\Policies;

use App\Models\Pemesanan;
use App\Models\User;

/**
 * Satu pemesanan menyangkut tiga pihak: penyewa yang mengajukan, pemilik kos
 * yang dipesan, dan admin. Masing-masing boleh melakukan hal yang berbeda,
 * sehingga tidak ada izin "umum" di sini.
 *
 * Catatan performa: beberapa method mengakses $pemesanan->kos. Muat relasi kos
 * lebih dulu (with('kos')) pada controller agar tidak terjadi query tambahan
 * untuk setiap baris saat memeriksa daftar.
 */
class PemesananPolicy
{
    public function view(User $user, Pemesanan $pemesanan): bool
    {
        return $this->penyewanya($user, $pemesanan)
            || $this->pemilikKosnya($user, $pemesanan)
            || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPenyewa();
    }

    /**
     * Menerima atau menolak pengajuan. Hanya pemilik kos yang bersangkutan;
     * admin sengaja tidak diberi izin ini agar keputusan komersial tetap
     * berada di tangan pemilik.
     */
    public function konfirmasi(User $user, Pemesanan $pemesanan): bool
    {
        return $this->pemilikKosnya($user, $pemesanan);
    }

    public function batalkan(User $user, Pemesanan $pemesanan): bool
    {
        return $this->penyewanya($user, $pemesanan);
    }

    public function bayar(User $user, Pemesanan $pemesanan): bool
    {
        return $this->penyewanya($user, $pemesanan);
    }

    public function ulas(User $user, Pemesanan $pemesanan): bool
    {
        return $this->penyewanya($user, $pemesanan) && $pemesanan->bolehDiulas();
    }

    private function penyewanya(User $user, Pemesanan $pemesanan): bool
    {
        return $user->id === $pemesanan->penyewa_id;
    }

    private function pemilikKosnya(User $user, Pemesanan $pemesanan): bool
    {
        return $user->id === $pemesanan->kos->pemilik_id;
    }
}
