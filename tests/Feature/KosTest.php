<?php

namespace Tests\Feature;

use App\Enums\StatusKos;
use App\Enums\TipeKos;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Titik acuan tetap untuk seluruh pengujian jarak di berkas ini.
     * Pada lintang ini, 0,001 derajat lintang kurang lebih 111 meter.
     */
    private const ACUAN_LAT = -3.537523;

    private const ACUAN_LNG = 118.990636;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ekost.kampus.lat', self::ACUAN_LAT);
        config()->set('ekost.kampus.lng', self::ACUAN_LNG);

        Storage::fake(config('ekost.disk_publik'));
    }

    private function kosPada(float $offsetLat, array $atribut = []): Kos
    {
        return Kos::factory()->create([
            'latitude' => self::ACUAN_LAT + $offsetLat,
            'longitude' => self::ACUAN_LNG,
            ...$atribut,
        ]);
    }

    public function test_tamu_dapat_melihat_daftar_kos(): void
    {
        Kos::factory()->count(3)->create();

        $this->getJson('/api/kos')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_kos_tidak_tayang_tidak_muncul_di_daftar_publik(): void
    {
        Kos::factory()->count(2)->create();
        Kos::factory()->nonaktif()->create();

        $this->getJson('/api/kos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * Inti FR-05. Kos di luar radius harus hilang dari hasil, dan sisanya
     * terurut dari yang terdekat.
     */
    public function test_pencarian_menyaring_dan_mengurutkan_berdasarkan_jarak(): void
    {
        $dekat = $this->kosPada(0.002, ['nama_kos' => 'Kos Dekat']);      // ~222 m
        $sedang = $this->kosPada(0.007, ['nama_kos' => 'Kos Sedang']);    // ~779 m
        $this->kosPada(0.020, ['nama_kos' => 'Kos Jauh']);                // ~2226 m

        $response = $this->getJson('/api/kos/cari?radius=1000')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame($dekat->id, $response->json('data.0.id'));
        $this->assertSame($sedang->id, $response->json('data.1.id'));

        // Jarak yang dilaporkan harus mendekati hitungan manual.
        $this->assertEqualsWithDelta(222, $response->json('data.0.jarak_meter'), 15);
        $this->assertEqualsWithDelta(779, $response->json('data.1.jarak_meter'), 15);
    }

    public function test_hasil_pencarian_menyebut_jenis_jarak_yang_dipakai(): void
    {
        $this->kosPada(0.001);

        $this->getJson('/api/kos/cari?radius=1000')
            ->assertOk()
            ->assertJsonPath('acuan.jenis_jarak', 'garis_lurus');
    }

    /**
     * Bila koordinat kampus belum diisi dan klien tidak mengirim titik acuan,
     * sistem harus menolak dengan jelas — bukan diam-diam mencari dari titik
     * 0,0 dan mengembalikan hasil kosong.
     */
    public function test_pencarian_ditolak_bila_titik_acuan_tidak_tersedia(): void
    {
        config()->set('ekost.kampus.lat', null);
        config()->set('ekost.kampus.lng', null);

        $this->getJson('/api/kos/cari')
            ->assertStatus(422)
            ->assertJsonValidationErrors('lat');
    }

    public function test_pencarian_dapat_disaring_menurut_tipe_kos(): void
    {
        $this->kosPada(0.001, ['tipe_kos' => TipeKos::Putri]);
        $this->kosPada(0.002, ['tipe_kos' => TipeKos::Putra]);

        $this->getJson('/api/kos/cari?radius=1000&tipe_kos=putri')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tipe_kos', 'putri');
    }

    public function test_penyewa_tidak_dapat_menambah_kos(): void
    {
        $penyewa = User::factory()->create();

        $this->actingAs($penyewa)
            ->postJson('/api/kos', $this->dataKosValid())
            ->assertForbidden();
    }

    public function test_pemilik_dapat_menambah_kos_dan_sisa_kamar_mengikuti_jumlah_kamar(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $this->actingAs($pemilik)
            ->postJson('/api/kos', [...$this->dataKosValid(), 'jumlah_kamar' => 8])
            ->assertCreated()
            ->assertJsonPath('data.sisa_kamar', 8);
    }

    /**
     * Angka sisa_kamar yang dikirim saat pembuatan harus diabaikan. Stok kamar
     * dikendalikan alur pemesanan, bukan angka yang diketik pemilik.
     */
    public function test_sisa_kamar_tidak_dapat_ditetapkan_saat_pembuatan(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $this->actingAs($pemilik)
            ->postJson('/api/kos', [
                ...$this->dataKosValid(),
                'jumlah_kamar' => 5,
                'sisa_kamar' => 99,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sisa_kamar', 5);
    }

    /**
     * Otorisasi tingkat objek. Keduanya berperan pemilik, sehingga middleware
     * role meloloskan keduanya — Policy yang harus menghentikannya.
     */
    public function test_pemilik_tidak_dapat_menyunting_kos_pemilik_lain(): void
    {
        $pemilikA = User::factory()->pemilik()->create();
        $kosB = Kos::factory()->create();

        $this->actingAs($pemilikA)
            ->putJson("/api/kos/{$kosB->id}", [
                ...$this->dataKosValid(),
                'sisa_kamar' => 1,
                'status' => StatusKos::Aktif->value,
            ])
            ->assertForbidden();
    }

    public function test_kos_yang_punya_riwayat_pemesanan_tidak_dapat_dihapus(): void
    {
        $kos = Kos::factory()->create();
        Pemesanan::factory()->create(['kos_id' => $kos->id]);

        $this->actingAs($kos->pemilik)
            ->deleteJson("/api/kos/{$kos->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('kos', ['id' => $kos->id]);
    }

    public function test_pemilik_dapat_mengunggah_foto_kos(): void
    {
        $kos = Kos::factory()->create();

        $this->actingAs($kos->pemilik)
            ->postJson("/api/kos/{$kos->id}/foto", [
                'foto' => [
                    UploadedFile::fake()->image('depan.jpg'),
                    UploadedFile::fake()->image('kamar.jpg'),
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, $kos->foto()->count());
    }

    public function test_unggahan_melebihi_batas_foto_ditolak(): void
    {
        config()->set('ekost.maksimum_foto_per_kos', 2);

        $kos = Kos::factory()->create();

        $this->actingAs($kos->pemilik)
            ->postJson("/api/kos/{$kos->id}/foto", [
                'foto' => [
                    UploadedFile::fake()->image('1.jpg'),
                    UploadedFile::fake()->image('2.jpg'),
                ],
            ])
            ->assertCreated();

        $this->actingAs($kos->pemilik)
            ->postJson("/api/kos/{$kos->id}/foto", [
                'foto' => [UploadedFile::fake()->image('3.jpg')],
            ])
            ->assertStatus(422);
    }

    public function test_berkas_bukan_gambar_ditolak_sebagai_foto_kos(): void
    {
        $kos = Kos::factory()->create();

        $this->actingAs($kos->pemilik)
            ->postJson("/api/kos/{$kos->id}/foto", [
                'foto' => [UploadedFile::fake()->create('skrip.php', 10, 'application/x-php')],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto.0');
    }

    /**
     * Foto milik kos lain tidak boleh terhapus hanya karena id kos pada URL
     * diganti dengan milik sendiri.
     */
    public function test_foto_kos_lain_tidak_dapat_dihapus_lewat_id_kos_sendiri(): void
    {
        $pemilikA = User::factory()->pemilik()->create();
        $kosA = Kos::factory()->create(['pemilik_id' => $pemilikA->id]);

        $kosB = Kos::factory()->create();
        $fotoB = $kosB->foto()->create([
            'path_foto' => 'foto-kos/milik-b.jpg',
            'urutan' => 1,
        ]);

        $this->actingAs($pemilikA)
            ->deleteJson("/api/kos/{$kosA->id}/foto/{$fotoB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('foto_kos', ['id' => $fotoB->id]);
    }

    /**
     * Halaman "Kos Saya" hanya boleh berisi kos milik pemilik yang sedang
     * masuk. Tanpa pemeriksaan ini, seorang pemilik dapat melihat — dan dari
     * situ menebak id — kos milik pemilik lain.
     */
    public function test_daftar_kos_saya_hanya_berisi_kos_milik_sendiri(): void
    {
        $pemilik = User::factory()->pemilik()->create();
        $miliknya = Kos::factory()->count(2)->create(['pemilik_id' => $pemilik->id]);

        // Kos milik pemilik lain. Tidak boleh ikut muncul.
        Kos::factory()->count(3)->create();

        $response = $this->actingAs($pemilik)
            ->getJson('/api/kos-saya')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            $miliknya->pluck('id')->all(),
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    /**
     * Berbeda dari daftar publik, kos yang tidak tayang tetap harus muncul di
     * halaman pemiliknya sendiri — kalau tidak, pemilik kehilangan satu-satunya
     * jalan untuk menayangkannya kembali.
     */
    public function test_kos_tidak_tayang_tetap_muncul_di_kos_saya(): void
    {
        $pemilik = User::factory()->pemilik()->create();
        Kos::factory()->create(['pemilik_id' => $pemilik->id]);
        Kos::factory()->nonaktif()->create(['pemilik_id' => $pemilik->id]);

        $this->actingAs($pemilik)
            ->getJson('/api/kos-saya')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_penyewa_tidak_dapat_membuka_daftar_kos_saya(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/kos-saya')
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function dataKosValid(): array
    {
        return [
            'nama_kos' => 'Kos Melati',
            'deskripsi' => 'Kos bersih dekat kampus.',
            'alamat' => 'Jl. Contoh No. 10, Banggae Timur, Majene',
            'latitude' => self::ACUAN_LAT + 0.001,
            'longitude' => self::ACUAN_LNG,
            'tipe_kos' => TipeKos::Putri->value,
            'harga_per_bulan' => 700000,
            'jumlah_kamar' => 10,
            'fasilitas' => ['WiFi', 'Kamar Mandi Dalam'],
        ];
    }
}
