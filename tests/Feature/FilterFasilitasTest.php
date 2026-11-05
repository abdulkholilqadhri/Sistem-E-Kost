<?php

namespace Tests\Feature;

use App\Models\Kos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-05 — penyaring fasilitas dan rentang harga pada pencarian kos.
 */
class FilterFasilitasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Titik acuan tetap, sama dengan yang dipakai KosTest.
     *
     * Disetel di sini, tidak diambil dari .env: koordinat kampus belum tentu
     * terisi pada lingkungan pengujian, dan (float) null adalah 0 — koordinat
     * 0,0 di Teluk Guinea, yang membuat seluruh kos uji berada di luar radius
     * dan setiap test gagal tanpa petunjuk penyebabnya.
     */
    private const ACUAN_LAT = -3.537523;

    private const ACUAN_LNG = 118.990636;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ekost.kampus.lat', self::ACUAN_LAT);
        config()->set('ekost.kampus.lng', self::ACUAN_LNG);
    }

    /**
     * @param  array<int, string>  $fasilitas
     */
    private function buatKos(array $fasilitas, int $harga = 700_000): Kos
    {
        return Kos::factory()->create([
            'latitude' => self::ACUAN_LAT,
            'longitude' => self::ACUAN_LNG,
            'harga_per_bulan' => $harga,
            'fasilitas' => $fasilitas,
        ]);
    }

    private function url(string $tambahan = ''): string
    {
        return '/api/kos/cari?lat='.self::ACUAN_LAT.'&lng='.self::ACUAN_LNG.'&radius=1000'.$tambahan;
    }

    public function test_menyaring_kos_yang_memiliki_fasilitas_yang_diminta(): void
    {
        $punya = $this->buatKos(['WiFi', 'AC', 'Kasur']);
        $this->buatKos(['Kasur', 'Lemari']);


        $this->getJson($this->url('&fasilitas[]=WiFi'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $punya->id);
    }

    /**
     * Mencentang dua fasilitas berarti dua syarat, bukan dua pilihan. Orang
     * yang mencentang WiFi dan AC tidak sedang meminta kos yang punya salah
     * satunya.
     */
    public function test_seluruh_fasilitas_yang_dipilih_harus_ada(): void
    {
        $lengkap = $this->buatKos(['WiFi', 'AC']);
        $this->buatKos(['WiFi']);
        $this->buatKos(['AC']);


        $this->getJson($this->url('&fasilitas[]=WiFi&fasilitas[]=AC'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lengkap->id);
    }

    public function test_tanpa_penyaring_fasilitas_seluruh_kos_tetap_muncul(): void
    {
        $this->buatKos(['WiFi']);
        $this->buatKos([]);


        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_kos_tanpa_fasilitas_sama_sekali_tidak_membuat_galat(): void
    {
        $this->buatKos([]);


        $this->getJson($this->url('&fasilitas[]=WiFi'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Nilai di luar daftar baku ditolak sebagai galat validasi, bukan
     * dijawab dengan hasil kosong yang tampak seperti memang tidak ada kos
     * yang cocok.
     */
    public function test_fasilitas_di_luar_daftar_baku_ditolak(): void
    {

        $this->getJson($this->url('&fasilitas[]=Kolam%20Renang'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fasilitas.0');
    }

    public function test_rentang_harga_menyaring_dari_dua_sisi(): void
    {
        $this->buatKos(['Kasur'], 400_000);
        $tengah = $this->buatKos(['Kasur'], 800_000);
        $this->buatKos(['Kasur'], 1_500_000);


        $this->getJson($this->url('&harga_min=600000&harga_maks=1000000'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tengah->id);
    }

    public function test_harga_maksimum_tidak_boleh_di_bawah_harga_minimum(): void
    {

        $this->getJson($this->url('&harga_min=900000&harga_maks=500000'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('harga_maks');
    }
}
