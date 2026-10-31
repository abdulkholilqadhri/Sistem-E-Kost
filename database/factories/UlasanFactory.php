<?php

namespace Database\Factories;

use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\Ulasan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ulasan>
 */
class UlasanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pemesanan_id' => Pemesanan::factory()->selesai(),
            'kos_id' => Kos::factory(),
            'penyewa_id' => User::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'komentar' => fake()->sentence(12),
        ];
    }
}
