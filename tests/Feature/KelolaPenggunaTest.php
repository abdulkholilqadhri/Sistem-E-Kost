<?php

namespace Tests\Feature;

use App\Enums\StatusAkun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-16 — Pengelolaan akun pengguna, beserta NFR-16.1 dan NFR-16.2.
 */
class KelolaPenggunaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_tidak_dapat_melihat_daftar_pengguna(): void
    {
        $this->getJson('/api/admin/pengguna')->assertUnauthorized();
    }

    public function test_penyewa_tidak_dapat_melihat_daftar_pengguna(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/pengguna')
            ->assertForbidden();
    }

    public function test_pemilik_tidak_dapat_melihat_daftar_pengguna(): void
    {
        $this->actingAs(User::factory()->pemilik()->create())
            ->getJson('/api/admin/pengguna')
            ->assertForbidden();
    }

    public function test_admin_dapat_melihat_daftar_pengguna(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/pengguna')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_daftar_dapat_disaring_berdasarkan_peran(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->pemilik()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/pengguna?role=pemilik')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_daftar_dapat_dicari_berdasarkan_nama_atau_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Jainuddin', 'email' => 'jainuddin@contoh.test']);
        User::factory()->create(['name' => 'Aril Malia', 'email' => 'aril@contoh.test']);

        $this->actingAs($admin)
            ->getJson('/api/admin/pengguna?q=jainuddin')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Jainuddin');
    }

    public function test_admin_dapat_menonaktifkan_akun(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
                'alasan' => 'Terindikasi memasang iklan kos palsu.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_akun', StatusAkun::Nonaktif->value);

        $target->refresh();

        $this->assertSame(StatusAkun::Nonaktif, $target->status_akun);
        $this->assertSame('Terindikasi memasang iklan kos palsu.', $target->alasan_nonaktif);
        $this->assertNotNull($target->status_diubah_pada);
    }

    public function test_penonaktifan_wajib_disertai_alasan(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alasan');
    }

    public function test_pengaktifan_kembali_membersihkan_alasan_lama(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->nonaktif()->create(['alasan_nonaktif' => 'Alasan lama']);

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Aktif->value,
            ])
            ->assertOk();

        $this->assertNull($target->refresh()->alasan_nonaktif);
    }

    /**
     * Middleware akun.aktif memeriksa status pada setiap permintaan, jadi
     * admin yang menonaktifkan dirinya sendiri akan terkunci di luar tanpa
     * ada orang lain yang bisa mengembalikannya.
     */
    public function test_admin_tidak_dapat_menonaktifkan_dirinya_sendiri(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$admin->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
                'alasan' => 'Coba-coba.',
            ])
            ->assertForbidden();

        $this->assertSame(StatusAkun::Aktif, $admin->refresh()->status_akun);
    }

    public function test_admin_tidak_dapat_menonaktifkan_admin_lain(): void
    {
        $admin = User::factory()->admin()->create();
        $adminLain = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$adminLain->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
                'alasan' => 'Perebutan kuasa.',
            ])
            ->assertForbidden();

        $this->assertSame(StatusAkun::Aktif, $adminLain->refresh()->status_akun);
    }

    public function test_penyewa_tidak_dapat_mengubah_status_siapa_pun(): void
    {
        $penyewa = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
                'alasan' => 'Tidak berwenang.',
            ])
            ->assertForbidden();

        $this->assertSame(StatusAkun::Aktif, $target->refresh()->status_akun);
    }

    /**
     * Inti dari FR-16: penonaktifan harus berlaku pada pengguna yang sedang
     * aktif memakai sistem, bukan hanya mencegah login berikutnya.
     */
    public function test_akun_yang_dinonaktifkan_langsung_kehilangan_akses(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($target)->getJson('/api/saya')->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Nonaktif->value,
                'alasan' => 'Melanggar aturan.',
            ])
            ->assertOk();

        $this->actingAs($target->refresh())->getJson('/api/saya')->assertForbidden();
    }

    public function test_peran_tidak_dapat_diubah_lewat_endpoint_ini(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengguna/{$target->id}/status", [
                'status_akun' => StatusAkun::Aktif->value,
                'role' => 'admin',
            ])
            ->assertOk();

        $this->assertFalse($target->refresh()->isAdmin());
    }
}
