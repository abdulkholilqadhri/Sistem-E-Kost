<?php

namespace App\Enums;

/**
 * Status pengajuan menjadi pemilik kos (FR-14, FR-15).
 *
 * Transisi sah:
 *   Menunggu -> Disetujui  (admin menyetujui; role user berubah menjadi Pemilik)
 *   Menunggu -> Ditolak    (admin menolak; role user tetap Penyewa)
 *
 * Disetujui dan Ditolak bersifat final. Pengguna yang ditolak dapat
 * mengirim pengajuan baru, bukan mengubah pengajuan yang lama.
 */
enum StatusPengajuan: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function final(): bool
    {
        return $this !== self::Menunggu;
    }

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Verifikasi',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }
}
