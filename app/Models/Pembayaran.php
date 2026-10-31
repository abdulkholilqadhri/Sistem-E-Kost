<?php

namespace App\Models;

use App\Enums\StatusPembayaran;
use Database\Factories\PembayaranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * path_bukti disembunyikan dari serialisasi JSON. Bukti transfer memuat nomor
 * rekening; berkasnya hanya boleh diambil lewat controller yang memeriksa
 * apakah pengguna adalah penyewa pemilik pesanan atau pemilik kos terkait.
 */
#[Fillable(['jumlah_bayar', 'path_bukti', 'nama_pengirim', 'tanggal_transfer'])]
#[Hidden(['path_bukti'])]
class Pembayaran extends Model
{
    /** @use HasFactory<PembayaranFactory> */
    use HasFactory;

    protected $table = 'pembayaran';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_bayar' => 'integer',
            'tanggal_transfer' => 'date',
            'status' => StatusPembayaran::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Pemesanan, $this>
     */
    public function pemesanan(): BelongsTo
    {
        return $this->belongsTo(Pemesanan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
