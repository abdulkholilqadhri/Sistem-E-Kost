<?php

namespace Tests\Feature;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPemesanan;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PemesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('ekost.disk_privat'));
    }

    private function kos(int $jumlahKamar = 3, ?int $sisaKamar = null): Kos
    {
        return Kos::factory()->create([
            'jumlah_kamar' => $jumlahKamar,
            'sisa_kamar' => $sisaKamar ?? $jumlahKamar,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dataPesanan(Kos $kos): array
    {
        return [
            'kos_id' => $kos->id,
            'tanggal_mulai' => today()->addDays(3)->toDateString(),
            'durasi_bulan' => 3,
        ];
    }

    // ---------------------------------------------------------------- ajukan

    public function test_penyewa_dapat_mengajukan_pesanan(): void
    {
        $kos = $this->kos();
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pemesanan', $this->dataPesanan($kos))
            ->assertCreated()
            ->assertJsonPath('data.status', StatusPemesanan::MenungguKonfirmasi->value)
            ->assertJsonPath('data.total_harga', $kos->harga_per_bulan * 3);
    }

    /**
     * Kamar belum berkurang saat pesanan baru diajukan. Pengurangan terjadi
     * saat pemilik menerima.
     */
    public function test_pengajuan_belum_mengurangi_stok_kamar(): void
    {
        $kos = $this->kos(sisaKamar: 3);
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pemesanan', $this->dataPesanan($kos))
            ->assertCreated();

        $this->assertSame(3, $kos->fresh()->sisa_kamar);
    }

    public function test_harga_disalin_sebagai_snapshot_dan_tidak_ikut_berubah(): void
    {
        $kos = $this->kos();
        $hargaAwal = $kos->harga_per_bulan;
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pemesanan', $this->dataPesanan($kos))
            ->assertCreated();

        $kos->update(['harga_per_bulan' => $hargaAwal + 500_000]);

        $this->assertSame($hargaAwal, Pemesanan::firstOrFail()->harga_per_bulan);
    }

    public function test_kos_yang_penuh_tidak_dapat_dipesan(): void
    {
        $kos = $this->kos(jumlahKamar: 2, sisaKamar: 0);
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pemesanan', $this->dataPesanan($kos))
            ->assertStatus(422);
    }

    public function test_penyewa_tidak_dapat_memesan_kos_yang_sama_dua_kali(): void
    {
        $kos = $this->kos();
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)->postJson('/api/pemesanan', $this->dataPesanan($kos))->assertCreated();
        $this->actingAs($penyewa)->postJson('/api/pemesanan', $this->dataPesanan($kos))->assertStatus(422);
    }

    public function test_pemilik_kos_tidak_dapat_mengajukan_pesanan(): void
    {
        $kos = $this->kos();
        $pemilikLain = User::factory()->pemilik()->create();

        $this->actingAs($pemilikLain)
            ->postJson('/api/pemesanan', $this->dataPesanan($kos))
            ->assertForbidden();
    }

    public function test_tanggal_mulai_di_masa_lalu_ditolak(): void
    {
        $kos = $this->kos();
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/pemesanan', [
                ...$this->dataPesanan($kos),
                'tanggal_mulai' => today()->subDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tanggal_mulai');
    }

    // ------------------------------------------------------------- keputusan

    public function test_pemilik_menerima_pesanan_dan_stok_berkurang(): void
    {
        $kos = $this->kos(sisaKamar: 2);
        $pemesanan = Pemesanan::factory()->create(['kos_id' => $kos->id]);

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pemesanan/{$pemesanan->id}/keputusan", ['keputusan' => 'terima'])
            ->assertOk()
            ->assertJsonPath('data.status', StatusPemesanan::MenungguPembayaran->value);

        $this->assertSame(1, $kos->fresh()->sisa_kamar);
        $this->assertNotNull($pemesanan->fresh()->batas_pembayaran);
    }

    public function test_pesanan_tidak_dapat_diterima_bila_kamar_sudah_habis(): void
    {
        $kos = $this->kos(jumlahKamar: 1, sisaKamar: 0);
        $pemesanan = Pemesanan::factory()->create(['kos_id' => $kos->id]);

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pemesanan/{$pemesanan->id}/keputusan", ['keputusan' => 'terima'])
            ->assertStatus(422);

        $this->assertSame(
            StatusPemesanan::MenungguKonfirmasi,
            $pemesanan->fresh()->status,
        );
    }

    public function test_penolakan_tidak_mengubah_stok_dan_wajib_beralasan(): void
    {
        $kos = $this->kos(sisaKamar: 2);
        $pemesanan = Pemesanan::factory()->create(['kos_id' => $kos->id]);

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pemesanan/{$pemesanan->id}/keputusan", ['keputusan' => 'tolak'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('catatan');

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pemesanan/{$pemesanan->id}/keputusan", [
                'keputusan' => 'tolak',
                'catatan' => 'Kamar sudah dijanjikan ke penyewa lain.',
            ])
            ->assertOk();

        $this->assertSame(StatusPemesanan::Ditolak, $pemesanan->fresh()->status);
        $this->assertSame(2, $kos->fresh()->sisa_kamar);
    }

    /**
     * Otorisasi tingkat objek pada pemesanan: pemilik kos lain tidak boleh
     * memutuskan pesanan yang bukan untuk kosnya.
     */
    public function test_pemilik_lain_tidak_dapat_memutuskan_pesanan(): void
    {
        $kos = $this->kos();
        $pemesanan = Pemesanan::factory()->create(['kos_id' => $kos->id]);
        $pemilikLain = User::factory()->pemilik()->create();

        $this->actingAs($pemilikLain)
            ->postJson("/api/pemesanan/{$pemesanan->id}/keputusan", ['keputusan' => 'terima'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- batal

    public function test_pembatalan_sebelum_dikonfirmasi_tidak_mengubah_stok(): void
    {
        $kos = $this->kos(sisaKamar: 3);
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->create([
            'kos_id' => $kos->id,
            'penyewa_id' => $penyewa->id,
        ]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/batal")
            ->assertOk();

        $this->assertSame(StatusPemesanan::Dibatalkan, $pemesanan->fresh()->status);
        $this->assertSame(3, $kos->fresh()->sisa_kamar);
    }

    public function test_pembatalan_setelah_dikonfirmasi_mengembalikan_kamar(): void
    {
        $kos = $this->kos(jumlahKamar: 3, sisaKamar: 2);
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos->id,
            'penyewa_id' => $penyewa->id,
        ]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/batal")
            ->assertOk();

        $this->assertSame(3, $kos->fresh()->sisa_kamar);
    }

    public function test_pesanan_selesai_tidak_dapat_dibatalkan(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->selesai()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/batal")
            ->assertStatus(422);
    }

    public function test_penyewa_lain_tidak_dapat_membatalkan_pesanan_orang(): void
    {
        $pemesanan = Pemesanan::factory()->create();
        $orangLain = User::factory()->create();

        $this->actingAs($orangLain)
            ->postJson("/api/pemesanan/{$pemesanan->id}/batal")
            ->assertForbidden();
    }

    // ----------------------------------------------------------- pembayaran

    public function test_bukti_tidak_dapat_diunggah_sebelum_pesanan_diterima(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
                'jumlah_bayar' => 500000,
            ])
            ->assertStatus(422);
    }

    public function test_unggah_bukti_memindahkan_pesanan_ke_menunggu_verifikasi(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
                'jumlah_bayar' => 500000,
                'nama_pengirim' => 'Budi Santoso',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', StatusPembayaran::Menunggu->value);

        $this->assertSame(StatusPemesanan::MenungguVerifikasi, $pemesanan->fresh()->status);
    }

    public function test_path_bukti_tidak_pernah_muncul_di_respons(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create(['penyewa_id' => $penyewa->id]);

        $response = $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
                'jumlah_bayar' => 500000,
            ])->assertCreated();

        $this->assertStringNotContainsString('path_bukti', $response->getContent());
        $this->assertStringNotContainsString('bukti-bayar/', $response->getContent());
    }

    public function test_verifikasi_pembayaran_mengaktifkan_pesanan(): void
    {
        $kos = $this->kos(jumlahKamar: 3, sisaKamar: 2);
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos->id,
            'penyewa_id' => $penyewa->id,
        ]);

        $this->actingAs($penyewa)->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            'jumlah_bayar' => 500000,
        ])->assertCreated();

        $pembayaran = $pemesanan->fresh()->pembayaran;

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pembayaran/{$pembayaran->id}/verifikasi", ['keputusan' => 'setujui'])
            ->assertOk();

        $this->assertSame(StatusPemesanan::Aktif, $pemesanan->fresh()->status);
        $this->assertSame(StatusPembayaran::Terverifikasi, $pembayaran->fresh()->status);

        // Kamar tetap ditahan selama pesanan aktif.
        $this->assertSame(2, $kos->fresh()->sisa_kamar);
    }

    /**
     * Bukti yang ditolak mengembalikan pesanan ke tahap pembayaran, tetapi
     * kamarnya tetap ditahan supaya penyewa punya kesempatan memperbaiki
     * bukti tanpa kehilangan kamar.
     */
    public function test_bukti_ditolak_mengembalikan_pesanan_ke_tahap_pembayaran(): void
    {
        $kos = $this->kos(jumlahKamar: 3, sisaKamar: 2);
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos->id,
            'penyewa_id' => $penyewa->id,
        ]);

        $this->actingAs($penyewa)->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            'jumlah_bayar' => 500000,
        ])->assertCreated();

        $pembayaran = $pemesanan->fresh()->pembayaran;

        $this->actingAs($kos->pemilik)
            ->postJson("/api/pembayaran/{$pembayaran->id}/verifikasi", [
                'keputusan' => 'tolak',
                'catatan' => 'Nominal transfer tidak sesuai tagihan.',
            ])
            ->assertOk();

        $this->assertSame(StatusPemesanan::MenungguPembayaran, $pemesanan->fresh()->status);
        $this->assertSame(StatusPembayaran::Ditolak, $pembayaran->fresh()->status);
        $this->assertSame(2, $kos->fresh()->sisa_kamar);
    }

    public function test_pemilik_lain_tidak_dapat_memverifikasi_pembayaran(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            'jumlah_bayar' => 500000,
        ])->assertCreated();

        $pembayaran = $pemesanan->fresh()->pembayaran;
        $pemilikLain = User::factory()->pemilik()->create();

        $this->actingAs($pemilikLain)
            ->postJson("/api/pembayaran/{$pembayaran->id}/verifikasi", ['keputusan' => 'setujui'])
            ->assertForbidden();
    }

    /**
     * Berkas bukti transfer memuat nomor rekening. Hanya tiga pihak yang boleh
     * membukanya: penyewa pemilik pesanan, pemilik kos, dan admin.
     */
    public function test_berkas_bukti_hanya_dapat_dibuka_pihak_yang_berkepentingan(): void
    {
        $kos = $this->kos();
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos->id,
            'penyewa_id' => $penyewa->id,
        ]);

        $this->actingAs($penyewa)->postJson("/api/pemesanan/{$pemesanan->id}/pembayaran", [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            'jumlah_bayar' => 500000,
        ])->assertCreated();

        $pembayaran = $pemesanan->fresh()->pembayaran;

        $this->actingAs($penyewa)->get("/api/pembayaran/{$pembayaran->id}/bukti")->assertOk();
        $this->actingAs($kos->pemilik)->get("/api/pembayaran/{$pembayaran->id}/bukti")->assertOk();
        $this->actingAs(User::factory()->admin()->create())
            ->get("/api/pembayaran/{$pembayaran->id}/bukti")->assertOk();

        $this->actingAs(User::factory()->create())
            ->get("/api/pembayaran/{$pembayaran->id}/bukti")->assertForbidden();
    }

    // ------------------------------------------------------ tugas terjadwal

    public function test_perintah_kedaluwarsa_membatalkan_dan_mengembalikan_kamar(): void
    {
        $kos = $this->kos(jumlahKamar: 3, sisaKamar: 2);
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos->id,
            'batas_pembayaran' => now()->subHour(),
        ]);

        $this->artisan('pemesanan:kedaluwarsa')->assertSuccessful();

        $this->assertSame(StatusPemesanan::Dibatalkan, $pemesanan->fresh()->status);
        $this->assertSame(3, $kos->fresh()->sisa_kamar);
    }

    public function test_perintah_kedaluwarsa_tidak_menyentuh_pesanan_yang_belum_lewat(): void
    {
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'batas_pembayaran' => now()->addHours(5),
        ]);

        $this->artisan('pemesanan:kedaluwarsa')->assertSuccessful();

        $this->assertSame(StatusPemesanan::MenungguPembayaran, $pemesanan->fresh()->status);
    }

    public function test_perintah_selesaikan_menutup_sewa_yang_berakhir(): void
    {
        $kos = $this->kos(jumlahKamar: 3, sisaKamar: 2);
        $pemesanan = Pemesanan::factory()->aktif()->create([
            'kos_id' => $kos->id,
            'tanggal_selesai' => today()->subDay(),
        ]);

        $this->artisan('pemesanan:selesaikan')->assertSuccessful();

        $this->assertSame(StatusPemesanan::Selesai, $pemesanan->fresh()->status);
        $this->assertSame(3, $kos->fresh()->sisa_kamar);
    }

    /**
     * Pengembalian kamar tidak boleh membuat stok melebihi jumlah kamar yang
     * benar-benar ada, bahkan bila data sempat melenceng.
     */
    public function test_pengembalian_kamar_tidak_melebihi_jumlah_kamar(): void
    {
        $kos = $this->kos(jumlahKamar: 2, sisaKamar: 2);
        $pemesanan = Pemesanan::factory()->aktif()->create([
            'kos_id' => $kos->id,
            'tanggal_selesai' => today()->subDay(),
        ]);

        $this->artisan('pemesanan:selesaikan')->assertSuccessful();

        $this->assertSame(2, $kos->fresh()->sisa_kamar);
    }
}
