<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Database\Factories\PengajuanPemilikFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Path berkas KTP disembunyikan dari serialisasi JSON. Berkas itu hanya boleh
 * diakses melalui controller yang memeriksa peran admin, bukan dengan menebak
 * path-nya dari respons API.
 */
#[Fillable(['nama_lengkap', 'alamat', 'path_ktp', 'path_dokumen_kepemilikan'])]
#[Hidden(['path_ktp', 'path_dokumen_kepemilikan'])]
class PengajuanPemilik extends Model
{
    /** @use HasFactory<PengajuanPemilikFactory> */
    use HasFactory;

    protected $table = 'pengajuan_pemilik';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
