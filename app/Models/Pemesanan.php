<?php

namespace App\Models;

use App\Enums\StatusPemesanan;
use Database\Factories\PemesananFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kolom status sengaja tidak fillable. Perubahan status hanya boleh lewat
 * PemesananService yang memvalidasi transisi terhadap state machine dan
 * menyesuaikan kos.sisa_kamar di dalam transaksi.
 */
#[Fillable([
    'tanggal_mulai',
    'durasi_bulan',
    'tanggal_selesai',
    'harga_per_bulan',
    'total_harga',
])]
class Pemesanan extends Model
{
    /** @use HasFactory<PemesananFactory> */
    use HasFactory;

    protected $table = 'pemesanan';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'durasi_bulan' => 'integer',
            'harga_per_bulan' => 'integer',
            'total_harga' => 'integer',
            'status' => StatusPemesanan::class,
            'dikonfirmasi_pada' => 'datetime',
            'batas_pembayaran' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Kos, $this>
     */
    public function kos(): BelongsTo
    {
        return $this->belongsTo(Kos::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penyewa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyewa_id');
    }

    /**
     * @return HasOne<Pembayaran, $this>
     */
    public function pembayaran(): HasOne
    {
        return $this->hasOne(Pembayaran::class);
    }

    /**
     * @return HasOne<Ulasan, $this>
     */
    public function ulasan(): HasOne
    {
        return $this->hasOne(Ulasan::class);
    }

    /**
     * Ulasan hanya boleh ditulis untuk pemesanan yang benar-benar selesai,
     * dan hanya sekali. SRS UC-09 menyebut "digunakan atau dipesan"; yang
     * dipakai di sini adalah "selesai", agar rating tidak dapat diberikan
     * oleh orang yang belum pernah menginap.
     */
    public function bolehDiulas(): bool
    {
        return $this->status === StatusPemesanan::Selesai
            && $this->ulasan()->doesntExist();
    }
}
