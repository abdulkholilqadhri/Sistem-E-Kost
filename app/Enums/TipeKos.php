<?php

namespace App\Enums;

/**
 * Tipe peruntukan kos. Dipakai sebagai filter pencarian dan badge pada kartu kos.
 */
enum TipeKos: string
{
    case Putra = 'putra';
    case Putri = 'putri';
    case Campur = 'campur';

    public function label(): string
    {
        return match ($this) {
            self::Putra => 'Khusus Putra',
            self::Putri => 'Khusus Putri',
            self::Campur => 'Campur',
        };
    }
}
