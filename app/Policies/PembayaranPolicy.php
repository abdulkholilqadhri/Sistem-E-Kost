<?php

namespace App\Policies;

use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\User;

/**
 * Bukti transfer memuat nomor rekening dan nama pengirim. Berkasnya disimpan
 * pada disk privat dan hanya dapat diunduh melalui controller yang memanggil
 * Policy ini (NFR-12.1 dan NFR-13.1).
 */
class PembayaranPolicy
{
    /**
     * Melihat data dan berkas bukti pembayaran.
     */
    public function view(User $user, Pembayaran $pembayaran): bool
    {
        return $user->id === $pembayaran->pemesanan->penyewa_id
            || $user->id === $pembayaran->pemesanan->kos->pemilik_id
            || $user->isAdmin();
    }

    /**
     * Menyetujui atau menolak bukti pembayaran. Hanya pemilik kos yang
     * bersangkutan, dan hanya selama statusnya masih menunggu. Pembayaran
     * yang sudah terverifikasi tidak dapat dibatalkan lewat jalur ini.
     */
    public function verifikasi(User $user, Pembayaran $pembayaran): bool
    {
        return $user->id === $pembayaran->pemesanan->kos->pemilik_id
            && $pembayaran->status === StatusPembayaran::Menunggu;
    }
}
