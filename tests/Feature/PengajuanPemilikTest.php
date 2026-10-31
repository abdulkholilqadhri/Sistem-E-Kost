<?php

namespace Tests\Feature;

use App\Enums\RoleUser;
use App\Enums\StatusPengajuan;
use App\Models\PengajuanPemilik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengajuanPemilikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('ekost.disk_privat'));
    }

    public function test_penyewa_dapat_mengajukan_diri_menjadi_pemilik(): void
    {
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pengajuan-pemilik', [
                'nama_lengkap' => 'Budi Santoso',
                'alamat' => 'Jl. Contoh No. 1, Majene',
                'ktp' => UploadedFile::fake()->image('ktp.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', StatusPengajuan::Menunggu->value);

        $pengajuan = PengajuanPemilik::firstOrFail();

        $this->assertSame($penyewa->id, $pengajuan->user_id);
        Storage::disk(config('ekost.disk_privat'))->assertExists($pengajuan->path_ktp);
    }

    /**
     * Berkas disimpan dengan nama yang dibangkitkan server. Nama asli dari
     * pengguna tidak boleh menjadi nama berkas di penyimpanan.
     */
    public function test_nama_berkas_asli_tidak_dipakai_di_penyimpanan(): void
    {
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)->postJson('/api/pengajuan-pemilik', [
            'nama_lengkap' => 'Budi Santoso',
            'ktp' => UploadedFile::fake()->image('ktp-budi-asli.jpg'),
        ])->assertCreated();

        $pengajuan = PengajuanPemilik::firstOrFail();

        $this->assertStringNotContainsString('ktp-budi-asli', $pengajuan->path_ktp);
    }

    public function test_path_berkas_tidak_pernah_muncul_di_respons(): void
    {
        $penyewa = User::factory()->create();

        $response = $this->actingAs($penyewa)->postJson('/api/pengajuan-pemilik', [
            'nama_lengkap' => 'Budi Santoso',
            'ktp' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertCreated();

        $this->assertStringNotContainsString('path_ktp', $response->getContent());
        $this->assertStringNotContainsString('pengajuan-pemilik/ktp', $response->getContent());
    }

    public function test_berkas_selain_gambar_dan_pdf_ditolak(): void
    {
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pengajuan-pemilik', [
                'nama_lengkap' => 'Budi Santoso',
                'ktp' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ktp');
    }

    public function test_tidak_dapat_mengajukan_dua_kali_selagi_menunggu(): void
    {
        $penyewa = User::factory()->create();
        PengajuanPemilik::factory()->for($penyewa)->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pengajuan-pemilik', [
                'nama_lengkap' => 'Budi Santoso',
                'ktp' => UploadedFile::fake()->image('ktp.jpg'),
            ])
            ->assertStatus(422);
    }

    public function test_pemilik_kos_tidak_dapat_mengajukan_lagi(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $this->actingAs($pemilik)
            ->postJson('/api/pengajuan-pemilik', [
                'nama_lengkap' => 'Budi Santoso',
                'ktp' => UploadedFile::fake()->image('ktp.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_persetujuan_admin_mengubah_peran_pemohon(): void
    {
        $admin = User::factory()->admin()->create();
        $pemohon = User::factory()->create();
        $pengajuan = PengajuanPemilik::factory()->for($pemohon)->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengajuan-pemilik/{$pengajuan->id}/verifikasi", [
                'keputusan' => StatusPengajuan::Disetujui->value,
            ])
            ->assertOk();

        $this->assertSame(StatusPengajuan::Disetujui, $pengajuan->fresh()->status);
        $this->assertSame(RoleUser::Pemilik, $pemohon->fresh()->role);
    }

    public function test_penolakan_tidak_mengubah_peran_dan_wajib_beralasan(): void
    {
        $admin = User::factory()->admin()->create();
        $pemohon = User::factory()->create();
        $pengajuan = PengajuanPemilik::factory()->for($pemohon)->create();

        // Tanpa alasan, ditolak oleh validasi.
        $this->actingAs($admin)
            ->postJson("/api/admin/pengajuan-pemilik/{$pengajuan->id}/verifikasi", [
                'keputusan' => StatusPengajuan::Ditolak->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('catatan_admin');

        $this->actingAs($admin)
            ->postJson("/api/admin/pengajuan-pemilik/{$pengajuan->id}/verifikasi", [
                'keputusan' => StatusPengajuan::Ditolak->value,
                'catatan_admin' => 'Foto KTP buram.',
            ])
            ->assertOk();

        $this->assertSame(StatusPengajuan::Ditolak, $pengajuan->fresh()->status);
        $this->assertSame(RoleUser::Penyewa, $pemohon->fresh()->role);
    }

    public function test_pengajuan_yang_sudah_final_tidak_dapat_diverifikasi_ulang(): void
    {
        $admin = User::factory()->admin()->create();
        $pengajuan = PengajuanPemilik::factory()->disetujui()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/pengajuan-pemilik/{$pengajuan->id}/verifikasi", [
                'keputusan' => StatusPengajuan::Ditolak->value,
                'catatan_admin' => 'Berubah pikiran.',
            ])
            ->assertForbidden();
    }

    public function test_bukan_admin_tidak_dapat_memverifikasi(): void
    {
        $penyewa = User::factory()->create();
        $pengajuan = PengajuanPemilik::factory()->create();

        $this->actingAs($penyewa)
            ->postJson("/api/admin/pengajuan-pemilik/{$pengajuan->id}/verifikasi", [
                'keputusan' => StatusPengajuan::Disetujui->value,
            ])
            ->assertForbidden();
    }

    /**
     * Inti perlindungan data KTP: hanya admin yang boleh membuka berkasnya,
     * bahkan pemohon sendiri tidak.
     */
    public function test_hanya_admin_yang_dapat_membuka_berkas_ktp(): void
    {
        $pemohon = User::factory()->create();
        $pengajuan = PengajuanPemilik::factory()->for($pemohon)->create([
            'path_ktp' => 'pengajuan-pemilik/ktp/uji.jpg',
        ]);

        Storage::disk(config('ekost.disk_privat'))->put('pengajuan-pemilik/ktp/uji.jpg', 'isi-berkas');

        $this->actingAs($pemohon)
            ->get("/api/admin/pengajuan-pemilik/{$pengajuan->id}/dokumen/ktp")
            ->assertForbidden();

        $penyewaLain = User::factory()->create();
        $this->actingAs($penyewaLain)
            ->get("/api/admin/pengajuan-pemilik/{$pengajuan->id}/dokumen/ktp")
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get("/api/admin/pengajuan-pemilik/{$pengajuan->id}/dokumen/ktp")
            ->assertOk();
    }

    public function test_pemohon_hanya_melihat_pengajuannya_sendiri(): void
    {
        $pemohon = User::factory()->create();
        $orangLain = User::factory()->create();

        PengajuanPemilik::factory()->for($pemohon)->create();
        $milikOrangLain = PengajuanPemilik::factory()->for($orangLain)->create();

        $this->actingAs($pemohon)
            ->getJson('/api/pengajuan-pemilik')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($pemohon)
            ->getJson("/api/pengajuan-pemilik/{$milikOrangLain->id}")
            ->assertForbidden();
    }
}
