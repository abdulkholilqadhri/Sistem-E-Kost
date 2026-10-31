<?php

namespace Tests\Feature;

use App\Enums\RoleUser;
use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrasi_membuat_akun_dengan_peran_penyewa(): void
    {
        $response = $this->postJson('/api/registrasi', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'no_hp' => '081234567890',
            'password' => 'kata-sandi-rahasia',
            'password_confirmation' => 'kata-sandi-rahasia',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'budi@example.test')->firstOrFail();

        $this->assertSame(RoleUser::Penyewa, $user->role);
        $this->assertSame(StatusAkun::Aktif, $user->status_akun);
    }

    /**
     * Menitipkan "role" pada permintaan registrasi tidak boleh berpengaruh.
     * Bila pemeriksaan ini gagal, siapa pun dapat mendaftar sebagai admin.
     */
    public function test_registrasi_tidak_dapat_menentukan_peran_sendiri(): void
    {
        $this->postJson('/api/registrasi', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'kata-sandi-rahasia',
            'password_confirmation' => 'kata-sandi-rahasia',
            'role' => 'admin',
            'status_akun' => 'aktif',
        ])->assertCreated();

        $user = User::where('email', 'penyusup@example.test')->firstOrFail();

        $this->assertSame(RoleUser::Penyewa, $user->role);
    }

    public function test_pengguna_dapat_masuk_dengan_kredensial_benar(): void
    {
        $user = User::factory()->create(['email' => 'pengguna@example.test']);

        $this->postJson('/api/login', [
            'email' => 'pengguna@example.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.email', 'pengguna@example.test');

        $this->assertAuthenticatedAs($user);
    }

    public function test_kata_sandi_salah_ditolak(): void
    {
        User::factory()->create(['email' => 'pengguna@example.test']);

        $this->postJson('/api/login', [
            'email' => 'pengguna@example.test',
            'password' => 'salah',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_dapat_masuk_meski_kata_sandi_benar(): void
    {
        User::factory()->nonaktif()->create(['email' => 'nonaktif@example.test']);

        $this->postJson('/api/login', [
            'email' => 'nonaktif@example.test',
            'password' => 'password',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    /**
     * Penonaktifan harus berefek pada sesi yang sedang berjalan, bukan hanya
     * pada login berikutnya.
     */
    public function test_akun_yang_dinonaktifkan_di_tengah_sesi_langsung_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/saya')->assertOk();

        // Penugasan langsung, bukan update(): status_akun sengaja tidak
        // termasuk atribut fillable, sehingga update() dengan array akan
        // diabaikan tanpa pesan apa pun. Hanya admin lewat FR-16 yang boleh
        // mengubah kolom ini pada alur sebenarnya.
        $user->status_akun = StatusAkun::Nonaktif;
        $user->save();

        $this->actingAs($user->fresh())->getJson('/api/saya')->assertForbidden();
    }

    public function test_percobaan_masuk_dibatasi_setelah_lima_kali_gagal(): void
    {
        User::factory()->create(['email' => 'target@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'target@example.test',
                'password' => 'tebakan-salah',
            ])->assertStatus(422);
        }

        // Percobaan keenam ditolak meskipun kata sandinya benar.
        $response = $this->postJson('/api/login', [
            'email' => 'target@example.test',
            'password' => 'password',
        ]);

        $response->assertStatus(422);

        // Pesannya menyebut sisa detik yang dapat bergeser antara 59 dan 60,
        // sehingga yang diperiksa hanya awal kalimatnya.
        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk',
            $response->json('errors.email.0'),
        );

        $this->assertGuest();
    }

    public function test_tamu_tidak_dapat_mengakses_rute_terlindungi(): void
    {
        $this->getJson('/api/saya')->assertUnauthorized();
    }

    public function test_pengguna_dapat_keluar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/logout')->assertOk();

        // Diperiksa pada guard 'web', bukan guard bawaan. Middleware
        // auth:sanctum menjadikan 'sanctum' sebagai guard bawaan selama
        // request berlangsung, dan guard itu menyimpan user hasil resolusi
        // di memori sampai request selesai. Yang benar-benar menentukan
        // pengguna masih masuk atau tidak adalah sesi pada guard 'web'.
        $this->assertGuest('web');
    }
}
