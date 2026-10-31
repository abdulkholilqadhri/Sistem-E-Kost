<?php

namespace Database\Factories;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanPemilik;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengajuanPemilik>
 */
class PengajuanPemilikFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nama_lengkap' => fake()->name(),
            'alamat' => fake()->address(),
            'path_ktp' => 'dokumen-pengajuan/ktp-contoh.jpg',
            'path_dokumen_kepemilikan' => null,
            'status' => StatusPengajuan::Menunggu,
        ];
    }

    public function disetujui(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPengajuan::Disetujui,
            'diverifikasi_pada' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPengajuan::Ditolak,
            'catatan_admin' => 'Berkas KTP tidak terbaca.',
            'diverifikasi_pada' => now(),
        ]);
    }
}
