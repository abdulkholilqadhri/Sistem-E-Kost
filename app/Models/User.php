<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleUser;
use App\Enums\StatusAkun;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Kolom role dan status_akun sengaja TIDAK dimasukkan ke dalam Fillable.
 * Keduanya hanya boleh diubah lewat jalur resmi: role melalui persetujuan
 * pengajuan oleh admin, status_akun melalui FR-16. Bila keduanya fillable,
 * satu permintaan registrasi yang menyisipkan "role=admin" sudah cukup untuk
 * mengambil alih sistem.
 */
#[Fillable(['name', 'email', 'no_hp', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUser::class,
            'status_akun' => StatusAkun::class,
        ];
    }

    /**
     * Kos yang dimiliki pengguna ini (hanya terisi bila role Pemilik).
     *
     * @return HasMany<Kos, $this>
     */
    public function kos(): HasMany
    {
        return $this->hasMany(Kos::class, 'pemilik_id');
    }

    /**
     * Pemesanan yang diajukan pengguna ini sebagai penyewa.
     *
     * @return HasMany<Pemesanan, $this>
     */
    public function pemesanan(): HasMany
    {
        return $this->hasMany(Pemesanan::class, 'penyewa_id');
    }

    /**
     * @return HasMany<PengajuanPemilik, $this>
     */
    public function pengajuanPemilik(): HasMany
    {
        return $this->hasMany(PengajuanPemilik::class);
    }

    /**
     * @return HasMany<Ulasan, $this>
     */
    public function ulasan(): HasMany
    {
        return $this->hasMany(Ulasan::class, 'penyewa_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === RoleUser::Admin;
    }

    public function isPemilik(): bool
    {
        return $this->role === RoleUser::Pemilik;
    }

    public function isPenyewa(): bool
    {
        return $this->role === RoleUser::Penyewa;
    }

    public function isAktif(): bool
    {
        return $this->status_akun === StatusAkun::Aktif;
    }
}
