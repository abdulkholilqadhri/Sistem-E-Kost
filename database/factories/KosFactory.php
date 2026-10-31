<?php

namespace Database\Factories;

use App\Enums\StatusKos;
use App\Enums\TipeKos;
use App\Models\Kos;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Seluruh data yang dihasilkan factory ini adalah DATA SIMULASI. Nama kos,
 * nama pemilik, dan alamat dibangkitkan Faker dengan locale id_ID, bukan
 * diambil dari kos atau orang sungguhan. Batasan nomor 3 pada Project Charter
 * memang menetapkan sistem diuji dengan data simulasi.
 *
 * @extends Factory<Kos>
 */
class KosFactory extends Factory
{
    /**
     * Titik contoh bila EKOST_KAMPUS_LAT/LNG belum diisi di .env.
     * Ini perkiraan kasar wilayah Majene, bukan koordinat kampus sebenarnya,
     * dan hanya dipakai agar seeder tetap menghasilkan titik yang berdekatan.
     */
    private const FALLBACK_LAT = -3.5400;

    private const FALLBACK_LNG = 118.9700;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pusatLat = config('ekost.kampus.lat') ?? self::FALLBACK_LAT;
        $pusatLng = config('ekost.kampus.lng') ?? self::FALLBACK_LNG;

        // Sebar titik dalam radius kira-kira 1,5 km dari pusat.
        // 0,0135 derajat kurang lebih 1,5 km untuk lintang.
        $jumlahKamar = fake()->numberBetween(4, 20);

        return [
            'pemilik_id' => User::factory()->pemilik(),
            'nama_kos' => 'Kos '.fake()->firstName().' '.fake()->randomElement(['Indah', 'Sejahtera', 'Asri', 'Permai', 'Bahagia']),
            'deskripsi' => fake()->paragraph(),
            'alamat' => fake()->streetAddress().', Banggae Timur, Majene',
            'latitude' => round($pusatLat + fake()->randomFloat(6, -0.0135, 0.0135), 8),
            'longitude' => round($pusatLng + fake()->randomFloat(6, -0.0135, 0.0135), 8),
            'tipe_kos' => fake()->randomElement(TipeKos::cases()),
            'harga_per_bulan' => fake()->numberBetween(5, 15) * 100_000,
            'jumlah_kamar' => $jumlahKamar,
            'sisa_kamar' => fake()->numberBetween(0, $jumlahKamar),
            'fasilitas' => fake()->randomElements(
                ['WiFi', 'Kamar Mandi Dalam', 'AC', 'Kasur', 'Lemari', 'Meja Belajar', 'Dapur Bersama', 'Parkir Motor'],
                fake()->numberBetween(3, 6),
            ),
            'status' => StatusKos::Aktif,
        ];
    }

    public function penuh(): static
    {
        return $this->state(fn (array $attributes) => [
            'sisa_kamar' => 0,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusKos::Nonaktif,
        ]);
    }
}
