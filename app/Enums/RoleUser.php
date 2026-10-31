<?php

namespace App\Enums;

/**
 * Peran pengguna dalam sistem E-Kost.
 *
 * Seluruh pengguna mendaftar sebagai Penyewa. Peran Pemilik hanya dapat
 * diperoleh melalui pengajuan yang disetujui admin (FR-14 dan FR-15),
 * tidak melalui pilihan saat registrasi.
 */
enum RoleUser: string
{
    case Penyewa = 'penyewa';
    case Pemilik = 'pemilik';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Penyewa => 'Calon Penyewa',
            self::Pemilik => 'Pemilik Kos',
            self::Admin => 'Admin',
        };
    }
}
