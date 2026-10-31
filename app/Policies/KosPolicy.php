<?php

namespace App\Policies;

use App\Enums\StatusKos;
use App\Models\Kos;
use App\Models\User;

/**
 * Otorisasi tingkat objek untuk kos (NFR-11.1).
 *
 * Middleware 'role:pemilik' hanya memastikan penggunanya seorang pemilik kos.
 * Policy inilah yang memastikan dia pemilik KOS INI. Tanpa Policy, setiap
 * pemilik kos dapat menyunting dan menghapus kos milik pemilik lain hanya
 * dengan mengganti angka id pada URL.
 */
class KosPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Kos $kos): bool
    {
        if ($kos->status === StatusKos::Aktif) {
            return true;
        }

        return $user !== null && ($this->miliknya($user, $kos) || $user->isAdmin());
    }

    public function create(User $user): bool
    {
        return $user->isPemilik();
    }

    public function update(User $user, Kos $kos): bool
    {
        return $this->miliknya($user, $kos) || $user->isAdmin();
    }

    public function delete(User $user, Kos $kos): bool
    {
        return $this->miliknya($user, $kos) || $user->isAdmin();
    }

    /**
     * Mengelola foto kos mengikuti izin yang sama dengan menyunting kos.
     */
    public function kelolaFoto(User $user, Kos $kos): bool
    {
        return $this->update($user, $kos);
    }

    private function miliknya(User $user, Kos $kos): bool
    {
        return $user->id === $kos->pemilik_id;
    }
}
