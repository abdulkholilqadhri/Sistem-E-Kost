<?php

namespace App\Models;

use Database\Factories\UlasanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rating', 'komentar'])]
class Ulasan extends Model
{
    /** @use HasFactory<UlasanFactory> */
    use HasFactory;

    protected $table = 'ulasan';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
}
