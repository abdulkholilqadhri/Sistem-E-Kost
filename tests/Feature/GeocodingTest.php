<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jawabanNominatim(): array
    {
        return [
            [
                'display_name' => 'Universitas Sulawesi Barat, Jalan Prof. Dr. Baharuddin Lopa, Majene',
                'lat' => '-3.537523',
                'lon' => '118.990636',
            ],
        ];
    }

    public function test_pemilik_dapat_mencari_alamat(): void
    {
        Http::fake(['*' => Http::response($this->jawabanNominatim())]);

        $this->actingAs(User::factory()->pemilik()->create())
            ->getJson('/api/geocode?q=universitas sulawesi barat')
            ->assertOk()
            ->assertJsonPath('data.0.lat', -3.537523)
            ->assertJsonPath('data.0.lng', 118.990636);
    }

    public function test_penyewa_tidak_dapat_memakai_pencarian_alamat(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/geocode?q=majene')
            ->assertForbidden();
    }

    public function test_kata_kunci_terlalu_pendek_ditolak(): void
    {
        $this->actingAs(User::factory()->pemilik()->create())
            ->getJson('/api/geocode?q=ma')
            ->assertStatus(422);
    }

    /**
     * Nominatim mewajibkan User-Agent yang mengidentifikasi aplikasi dan
     * memblokir permintaan tanpa itu. Dikunci lewat pengujian karena
     * kegagalannya baru terlihat setelah server diblokir.
     */
    public function test_permintaan_membawa_user_agent_aplikasi(): void
    {
        Http::fake(['*' => Http::response($this->jawabanNominatim())]);

        $this->actingAs(User::factory()->pemilik()->create())
            ->getJson('/api/geocode?q=majene')
            ->assertOk();

        Http::assertSent(
            fn ($request) => $request->hasHeader('User-Agent', config('ekost.nominatim.user_agent')),
        );
    }

    public function test_hasil_yang_sama_diambil_dari_cache(): void
    {
        Http::fake(['*' => Http::response($this->jawabanNominatim())]);

        $pemilik = User::factory()->pemilik()->create();

        $this->actingAs($pemilik)->getJson('/api/geocode?q=majene')->assertOk();
        $this->actingAs($pemilik)->getJson('/api/geocode?q=Majene')->assertOk();

        // Kata kunci dinormalkan menjadi huruf kecil sebelum jadi kunci cache,
        // sehingga perbedaan huruf besar-kecil tidak memicu permintaan baru.
        Http::assertSentCount(1);
    }

    public function test_layanan_mati_tidak_menjatuhkan_permintaan(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs(User::factory()->pemilik()->create())
            ->getJson('/api/geocode?q=majene')
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
