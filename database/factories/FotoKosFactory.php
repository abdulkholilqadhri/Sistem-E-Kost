<?php

namespace Database\Factories;

use App\Models\FotoKos;
use App\Models\Kos;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FotoKos>
 */
class FotoKosFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kos_id' => Kos::factory(),
            'path_foto' => 'foto-kos/contoh-'.fake()->numberBetween(1, 8).'.jpg',
            'keterangan' => fake()->randomElement(['Tampak depan', 'Kamar tidur', 'Kamar mandi', 'Dapur', 'Ruang tamu', 'Area parkir']),
            'urutan' => 0,
        ];
    }
}
