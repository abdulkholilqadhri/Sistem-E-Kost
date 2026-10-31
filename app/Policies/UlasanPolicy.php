<?php

namespace App\Policies;

use App\Models\Ulasan;
use App\Models\User;

class UlasanPolicy
{
    /**
     * Ulasan bersifat publik; siapa pun boleh membacanya tanpa login.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Ulasan $ulasan): bool
    {
        return true;
    }

    /**
     * Ulasan tidak boleh disunting setelah terkirim. Pemilik kos yang
     * menerima rating rendah tidak punya jalan menekan penyewa agar
     * memperbaikinya, dan riwayat penilaian tetap jujur.
     */
    public function update(User $user, Ulasan $ulasan): bool
    {
        return false;
    }

    /**
     * Penghapusan hanya untuk penyalahgunaan, dan hanya oleh admin.
     * Penulisnya sendiri tidak dapat menghapus agar rating tidak dapat
     * dibersihkan setelah terbit.
     */
    public function delete(User $user, Ulasan $ulasan): bool
    {
        return $user->isAdmin();
    }
}
