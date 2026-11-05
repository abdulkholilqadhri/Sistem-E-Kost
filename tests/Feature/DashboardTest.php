<?php

namespace Tests\Feature;

use App\Enums\StatusPengajuan;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\PengajuanPemilik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-03 — Dashboard.
 *
 * Yang diuji terutama bukan angkanya, melainkan pemisahan antarperan: satu
 * endpoint melayani tiga peran, dan kesalahan paling berbahaya di sana adalah
 * ringkasan milik peran lain yang bocor.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_tidak_dapat_membuka_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->actingAs(User::factory()->nonaktif()->create())
            ->getJson('/api/dashboard')
            ->assertForbidden();
    }

    public function test_penyewa_mendapat_ringkasan_pesanannya_sendiri(): void
    {
        $penyewa = User::factory()->create();
        $orangLain = User::factory()->create();
        $kos = Kos::factory()->create();

        Pemesanan::factory()->count(2)->aktif()->create([
            'penyewa_id' => $penyewa->id,
            'kos_id' => $kos->id,
        ]);

        // Milik orang lain. Kalau penyaringan penyewa_id hilang, angka di
        // bawah akan menjadi 5 dan test ini yang pertama jatuh.
        Pemesanan::factory()->count(3)->aktif()->create([
            'penyewa_id' => $orangLain->id,
            'kos_id' => $kos->id,
        ]);

        $jawaban = $this->actingAs($penyewa)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.peran', 'penyewa');

        $this->assertSame(2, $this->nilaiRingkasan($jawaban->json('data.ringkasan'), 'Sewa aktif'));
    }

    public function test_penyewa_tidak_menerima_angka_milik_admin(): void
    {
        User::factory()->count(4)->create();

        $jawaban = $this->actingAs(User::factory()->create())
            ->getJson('/api/dashboard')
            ->assertOk();

        $label = array_column($jawaban->json('data.ringkasan'), 'label');

        $this->assertNotContains('Total pengguna', $label);
        $this->assertNull($jawaban->json('data.antrean'));
    }

    public function test_pemilik_hanya_melihat_kos_miliknya(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        Kos::factory()->count(2)->create(['pemilik_id' => $pemilik->id]);
        Kos::factory()->count(5)->create();

        $jawaban = $this->actingAs($pemilik)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.peran', 'pemilik');

        $this->assertSame(2, $this->nilaiRingkasan($jawaban->json('data.ringkasan'), 'Kos tayang'));
    }

    public function test_pemilik_diberi_tahu_pesanan_yang_menunggu_keputusannya(): void
    {
        $pemilik = User::factory()->pemilik()->create();
        $kos = Kos::factory()->create(['pemilik_id' => $pemilik->id]);

        Pemesanan::factory()->create(['kos_id' => $kos->id]);

        $tindakan = $this->actingAs($pemilik)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->json('data.tindakan');

        $this->assertSame('pemilik-pesanan', $tindakan[0]['rute']);
    }

    public function test_admin_mendapat_ringkasan_sistem_dan_antrean_verifikasi(): void
    {
        $admin = User::factory()->admin()->create();

        PengajuanPemilik::factory()->count(3)->create(['status' => StatusPengajuan::Menunggu]);
        PengajuanPemilik::factory()->create(['status' => StatusPengajuan::Disetujui]);

        $jawaban = $this->actingAs($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.peran', 'admin');

        // Hanya yang berstatus menunggu yang masuk antrean.
        $this->assertCount(3, $jawaban->json('data.antrean'));
    }

    /**
     * Berkas KTP tidak boleh ikut terbawa ke dasbor. Modelnya sudah
     * menyembunyikannya, tetapi dasbor menyusun arraynya sendiri — jadi
     * perlindungan itu tidak berlaku otomatis di sini.
     */
    public function test_antrean_tidak_membocorkan_path_berkas(): void
    {
        $admin = User::factory()->admin()->create();
        PengajuanPemilik::factory()->create(['status' => StatusPengajuan::Menunggu]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonMissingPath('data.antrean.0.path_ktp')
            ->assertJsonMissingPath('data.antrean.0.path_dokumen_kepemilikan');
    }

    /**
     * @param  array<int, array<string, mixed>>  $ringkasan
     */
    private function nilaiRingkasan(array $ringkasan, string $label): ?int
    {
        foreach ($ringkasan as $butir) {
            if ($butir['label'] === $label) {
                return $butir['nilai'];
            }
        }

        return null;
    }
}
