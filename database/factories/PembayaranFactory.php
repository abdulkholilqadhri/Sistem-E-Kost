<?php

namespace Database\Factories;

use App\Enums\StatusPembayaran;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pemesanan_id' => Pemesanan::factory(),
            'jumlah_bayar' => fake()->numberBetween(5, 15) * 100_000,
            'path_bukti' => 'bukti-bayar/contoh.jpg',
            'nama_pengirim' => fake()->name(),
            'tanggal_transfer' => fake()->dateTimeBetween('-1 week', 'now'),
            'status' => StatusPembayaran::Menunggu,
        ];
    }

    public function terverifikasi(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Terverifikasi,
            'diverifikasi_pada' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Ditolak,
            'catatan' => 'Nominal transfer tidak sesuai tagihan.',
            'diverifikasi_pada' => now(),
        ]);
    }
}
