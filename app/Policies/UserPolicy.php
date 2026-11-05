<?php

namespace App\Policies;

use App\Models\User;

/**
 * FR-16 — Pengelolaan akun pengguna, dan NFR-16.1 yang membatasinya ke admin.
 */
class UserPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->isAdmin();
    }

    public function view(User $pengguna, User $target): bool
    {
        return $pengguna->isAdmin() || $pengguna->id === $target->id;
    }

    /**
     * Mengubah status aktif sebuah akun.
     *
     * DUA PEMBATASAN DI LUAR PEMERIKSAAN PERAN
     *
     * Admin tidak dapat menonaktifkan dirinya sendiri. Middleware akun.aktif
     * memeriksa status pada setiap permintaan, sehingga admin yang menonaktifkan
     * akunnya sendiri akan langsung terkunci di luar — dan tidak ada orang lain
     * yang bisa mengembalikannya kecuali lewat database.
     *
     * Admin juga tidak dapat menonaktifkan admin lain. Sistem ini tidak punya
     * tingkatan admin, jadi bila boleh, satu admin dapat menyingkirkan seluruh
     * admin lainnya dan sistem kehilangan pengelolanya tanpa jejak persetujuan.
     * Penonaktifan sesama admin dilakukan lewat database oleh yang memegang
     * servernya.
     */
    public function kelolaStatus(User $pengguna, User $target): bool
    {
        if (! $pengguna->isAdmin()) {
            return false;
        }

        if ($pengguna->id === $target->id) {
            return false;
        }

        return ! $target->isAdmin();
    }
}
