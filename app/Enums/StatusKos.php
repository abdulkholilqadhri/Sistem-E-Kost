<?php

namespace App\Enums;

/**
 * Status tayang kos. Kos nonaktif tidak muncul di peta maupun hasil pencarian
 * dan tidak dapat menerima pemesanan baru, tetapi pemesanan yang sudah
 * berjalan tetap berlaku.
 */
enum StatusKos: string
{
    case Aktif = 'aktif';
    case Nonaktif = 'nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Tayang',
            self::Nonaktif => 'Tidak Tayang',
        };
    }
}
