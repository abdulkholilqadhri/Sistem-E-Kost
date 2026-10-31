<?php

namespace App\Enums;

/**
 * Status akun pengguna. Dikelola admin melalui FR-16.
 *
 * Akun nonaktif tidak dapat login dan seluruh token Sanctum miliknya
 * dicabut saat penonaktifan.
 */
enum StatusAkun: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Nonaktif => 'Nonaktif',
        };
    }
}
