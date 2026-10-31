<?php

namespace Database\Factories;

use App\Enums\RoleUser;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'no_hp' => fake()->numerify('08##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleUser::Penyewa,
            'status_akun' => StatusAkun::Aktif,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pemilik(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleUser::Pemilik,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleUser::Admin,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_akun' => StatusAkun::Nonaktif,
        ]);
    }
}
