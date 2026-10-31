<?php

namespace App\Models;

use App\Enums\StatusKos;
use App\Enums\TipeKos;
use Database\Factories\KosFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nama_kos',
    'deskripsi',
    'alamat',
    'latitude',
    'longitude',
    'tipe_kos',
    'harga_per_bulan',
    'jumlah_kamar',
    'sisa_kamar',
    'fasilitas',
    'status',
])]
class Kos extends Model
{
    /** @use HasFactory<KosFactory> */
    use HasFactory;

    protected $table = 'kos';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'harga_per_bulan' => 'integer',
            'jumlah_kamar' => 'integer',
            'sisa_kamar' => 'integer',
            'fasilitas' => 'array',
            'tipe_kos' => TipeKos::class,
            'status' => StatusKos::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemilik_id');
    }

    /**
     * @return HasMany<FotoKos, $this>
     */
    public function foto(): HasMany
    {
        return $this->hasMany(FotoKos::class)->orderBy('urutan');
    }

    /**
     * @return HasMany<Pemesanan, $this>
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class);
    }

    /**
     * @return HasMany<Ulasan, $this>
     */
    public function ulasan(): HasMany
    {
        return $this->hasMany(Ulasan::class);
    }

    public function tersedia(): bool
    {
        return $this->status === StatusKos::Aktif && $this->sisa_kamar > 0;
    }

    /**
     * Hanya kos yang tayang. Dipakai pada seluruh endpoint publik supaya kos
     * nonaktif tidak pernah bocor ke peta maupun hasil pencarian.
     *
     * @param  Builder<Kos>  $query
     */
    public function scopeTayang(Builder $query): void
    {
        $query->where('status', StatusKos::Aktif);
    }
}
