<?php

namespace Database\Factories;

use App\Enums\StatusPemesanan;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pemesanan>
 */
class PemesananFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tanggalMulai = fake()->dateTimeBetween('-2 months', '+1 month');
        $durasi = fake()->numberBetween(1, 6);
        $harga = fake()->numberBetween(5, 15) * 100_000;

        return [
            'kos_id' => Kos::factory(),
            'penyewa_id' => User::factory(),
            'tanggal_mulai' => $tanggalMulai,
            'durasi_bulan' => $durasi,
            'tanggal_selesai' => (clone $tanggalMulai)->modify("+{$durasi} months"),
            'harga_per_bulan' => $harga,
            'total_harga' => $harga * $durasi,
            'status' => StatusPemesanan::MenungguKonfirmasi,
        ];
    }

    public function menungguPembayaran(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPemesanan::MenungguPembayaran,
            'dikonfirmasi_pada' => now(),
            'batas_pembayaran' => now()->addHours(config('ekost.batas_pembayaran_jam')),
        ]);
    }

    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPemesanan::Aktif,
            'dikonfirmasi_pada' => now(),
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPemesanan::Selesai,
            'dikonfirmasi_pada' => now()->subMonths(2),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPemesanan::Ditolak,
            'catatan_pemilik' => 'Kamar sudah dipesan penyewa lain.',
        ]);
    }
}
