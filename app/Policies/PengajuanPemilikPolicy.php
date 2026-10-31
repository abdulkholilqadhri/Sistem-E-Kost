<?php

namespace App\Policies;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanPemilik;
use App\Models\User;

/**
 * Pengajuan memuat berkas KTP, yang berisi NIK. Izin melihat berkasnya
 * dipisahkan dari izin melihat data pengajuan: pemohon boleh memantau status
 * pengajuannya sendiri, tetapi berkas KTP hanya dapat dibuka admin yang
 * memverifikasi.
 */
class PengajuanPemilikPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, PengajuanPemilik $pengajuan): bool
    {
        return $user->id === $pengajuan->user_id || $user->isAdmin();
    }

    /**
     * Hanya penyewa yang boleh mengajukan. Pemilik kos sudah terverifikasi,
     * dan admin tidak perlu mengajukan apa pun.
     */
    public function create(User $user): bool
    {
        return $user->isPenyewa();
    }

    /**
     * Membuka berkas KTP dan dokumen kepemilikan.
     */
    public function lihatDokumen(User $user, PengajuanPemilik $pengajuan): bool
    {
        return $user->isAdmin();
    }

    /**
     * Menyetujui atau menolak. Pengajuan yang sudah final tidak dapat
     * diverifikasi ulang; pemohon yang ditolak mengirim pengajuan baru.
     */
    public function verifikasi(User $user, PengajuanPemilik $pengajuan): bool
    {
        return $user->isAdmin() && $pengajuan->status === StatusPengajuan::Menunggu;
    }
}
