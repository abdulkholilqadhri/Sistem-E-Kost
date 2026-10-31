<?php

namespace Tests\Feature;

use App\Models\Kos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RuteTest extends TestCase
{
    use RefreshDatabase;

    private const ACUAN_LAT = -3.537523;

    private const ACUAN_LNG = 118.990636;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ekost.kampus.lat', self::ACUAN_LAT);
        config()->set('ekost.kampus.lng', self::ACUAN_LNG);

        Cache::flush();
    }

    private function kos(): Kos
    {
        return Kos::factory()->create([
            'latitude' => self::ACUAN_LAT + 0.004,
            'longitude' => self::ACUAN_LNG,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jawabanOsrm(float $jarak = 612.3, float $durasi = 150.7): array
    {
        return [
            'code' => 'Ok',
            'routes' => [[
                'distance' => $jarak,
                'duration' => $durasi,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => [[118.990636, -3.537523], [118.990636, -3.533523]],
                ],
            ]],
        ];
    }

    public function test_rute_dikembalikan_beserta_jarak_tempuh_dan_durasi(): void
    {
        Http::fake(['*' => Http::response($this->jawabanOsrm())]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")
            ->assertOk()
            ->assertJsonPath('tersedia', true)
            ->assertJsonPath('rute.jarak_tempuh_meter', 612)
            ->assertJsonPath('rute.durasi_detik', 151)
            ->assertJsonPath('rute.geometri.type', 'LineString');
    }

    /**
     * Jarak garis lurus tetap dikirim bersama rute, supaya antarmuka dapat
     * memberi label berbeda untuk dua angka yang memang berbeda artinya.
     */
    public function test_jarak_garis_lurus_selalu_disertakan(): void
    {
        Http::fake(['*' => Http::response($this->jawabanOsrm())]);

        $kos = $this->kos();

        $response = $this->getJson("/api/kos/{$kos->id}/rute")->assertOk();

        // 0,004 derajat lintang kurang lebih 445 meter.
        $this->assertEqualsWithDelta(445, $response->json('jarak_lurus_meter'), 15);
        $this->assertGreaterThan(
            $response->json('jarak_lurus_meter'),
            $response->json('rute.jarak_tempuh_meter'),
        );
    }

    /**
     * OSRM adalah server demo publik yang bisa mati kapan saja. Kegagalannya
     * tidak boleh menjatuhkan halaman (NFR-04.2).
     */
    public function test_layanan_rute_mati_tidak_menjatuhkan_permintaan(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")
            ->assertOk()
            ->assertJsonPath('tersedia', false)
            ->assertJsonPath('rute', null)
            ->assertJsonStructure(['jarak_lurus_meter']);
    }

    public function test_koneksi_gagal_juga_ditangani_dengan_anggun(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")
            ->assertOk()
            ->assertJsonPath('tersedia', false);
    }

    public function test_osrm_yang_tidak_menemukan_rute_ditangani(): void
    {
        Http::fake(['*' => Http::response(['code' => 'NoRoute', 'routes' => []])]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")
            ->assertOk()
            ->assertJsonPath('tersedia', false);
    }

    /**
     * Permintaan kedua untuk titik yang sama tidak boleh menembak OSRM lagi.
     * Server publik membatasi pemakaian, dan rute antara dua titik tetap tidak
     * berubah dari menit ke menit.
     */
    public function test_rute_yang_sama_diambil_dari_cache(): void
    {
        Http::fake(['*' => Http::response($this->jawabanOsrm())]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")->assertOk();
        $this->getJson("/api/kos/{$kos->id}/rute")->assertOk();

        Http::assertSentCount(1);
    }

    /**
     * OSRM memakai urutan bujur,lintang — kebalikan dari Leaflet dan basis
     * data. Tertukar akan menghasilkan rute di belahan bumi yang salah tanpa
     * pesan kesalahan apa pun, jadi urutannya dikunci oleh pengujian.
     */
    public function test_koordinat_dikirim_ke_osrm_dengan_urutan_bujur_lintang(): void
    {
        Http::fake(['*' => Http::response($this->jawabanOsrm())]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute")->assertOk();

        Http::assertSent(function ($request) use ($kos): bool {
            $diharapkan = sprintf(
                '%F,%F;%F,%F',
                self::ACUAN_LNG,
                self::ACUAN_LAT,
                $kos->longitude,
                $kos->latitude,
            );

            return str_contains($request->url(), $diharapkan);
        });
    }

    public function test_titik_acuan_dapat_dikirim_lewat_parameter(): void
    {
        Http::fake(['*' => Http::response($this->jawabanOsrm())]);

        $kos = $this->kos();

        $this->getJson("/api/kos/{$kos->id}/rute?lat=-3.540000&lng=118.995000")
            ->assertOk()
            ->assertJsonPath('tersedia', true);

        Http::assertSent(fn ($request) => str_contains($request->url(), '118.995000,-3.540000'));
    }
}
