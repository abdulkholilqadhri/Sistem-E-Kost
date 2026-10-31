<?php

namespace App\Models;

use Database\Factories\FotoKosFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path_foto', 'keterangan', 'urutan'])]
class FotoKos extends Model
{
    /** @use HasFactory<FotoKosFactory> */
    use HasFactory;

    protected $table = 'foto_kos';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Kos, $this>
     */
    public function kos(): BelongsTo
    {
        return $this->belongsTo(Kos::class);
    }
}
