<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\Ulasan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UlasanTest extends TestCase
{
    use RefreshDatabase;

    public function test_penyewa_dapat_mengulas_pesanan_yang_selesai(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->selesai()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", [
                'rating' => 5,
                'komentar' => 'Kamarnya bersih dan pemiliknya ramah.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('ulasan', [
            'pemesanan_id' => $pemesanan->id,
            'kos_id' => $pemesanan->kos_id,
            'penyewa_id' => $penyewa->id,
        ]);
    }

    /**
     * Inti FR-09: rating hanya berarti bila penulisnya benar-benar pernah
     * menginap. Pesanan yang belum selesai tidak boleh diulas.
     */
    public function test_pesanan_yang_belum_selesai_tidak_dapat_diulas(): void
    {
        $penyewa = User::factory()->create();

        foreach (['menungguPembayaran', 'aktif', 'ditolak'] as $keadaan) {
            $pemesanan = Pemesanan::factory()->{$keadaan}()->create(['penyewa_id' => $penyewa->id]);

            $this->actingAs($penyewa)
                ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", ['rating' => 5])
                ->assertForbidden();
        }
    }

    public function test_orang_lain_tidak_dapat_mengulas_pesanan_bukan_miliknya(): void
    {
        $pemesanan = Pemesanan::factory()->selesai()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_satu_pesanan_hanya_dapat_diulas_sekali(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->selesai()->create(['penyewa_id' => $penyewa->id]);

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", ['rating' => 5])
            ->assertCreated();

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", ['rating' => 1])
            ->assertForbidden();

        $this->assertSame(1, Ulasan::count());
    }

    public function test_rating_di_luar_satu_sampai_lima_ditolak(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->selesai()->create(['penyewa_id' => $penyewa->id]);

        foreach ([0, 6, -1] as $nilai) {
            $this->actingAs($penyewa)
                ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", ['rating' => $nilai])
                ->assertStatus(422)
                ->assertJsonValidationErrors('rating');
        }
    }

    /**
     * kos_id disalin dari pemesanan, bukan diterima dari permintaan. Tanpa
     * itu, penyewa dapat menitipkan ulasan pada kos yang tidak pernah ia
     * tempati.
     */
    public function test_kos_yang_diulas_mengikuti_pemesanan_bukan_permintaan(): void
    {
        $penyewa = User::factory()->create();
        $pemesanan = Pemesanan::factory()->selesai()->create(['penyewa_id' => $penyewa->id]);
        $kosLain = Kos::factory()->create();

        $this->actingAs($penyewa)
            ->postJson("/api/pemesanan/{$pemesanan->id}/ulasan", [
                'rating' => 1,
                'kos_id' => $kosLain->id,
            ])
            ->assertCreated();

        $this->assertSame($pemesanan->kos_id, Ulasan::firstOrFail()->kos_id);
    }

    public function test_ulasan_sebuah_kos_dapat_dibaca_tanpa_login(): void
    {
        $kos = Kos::factory()->create();
        Ulasan::factory()->count(3)->create(['kos_id' => $kos->id]);

        $this->getJson("/api/kos/{$kos->id}/ulasan")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * Ulasan terbuka untuk umum, sehingga tidak boleh membocorkan data
     * pribadi penulisnya. Yang keluar hanya namanya.
     */
    public function test_email_penulis_tidak_ikut_tersaji(): void
    {
        $kos = Kos::factory()->create();
        $penulis = User::factory()->create(['email' => 'rahasia@example.test']);
        Ulasan::factory()->create(['kos_id' => $kos->id, 'penyewa_id' => $penulis->id]);

        $response = $this->getJson("/api/kos/{$kos->id}/ulasan")->assertOk();

        $this->assertStringNotContainsString('rahasia@example.test', $response->getContent());
        $this->assertSame($penulis->name, $response->json('data.0.penulis.nama'));
    }

    public function test_rating_rata_rata_muncul_pada_detail_kos(): void
    {
        $kos = Kos::factory()->create();
        Ulasan::factory()->create(['kos_id' => $kos->id, 'rating' => 5]);
        Ulasan::factory()->create(['kos_id' => $kos->id, 'rating' => 3]);

        $this->getJson("/api/kos/{$kos->id}")
            ->assertOk()
            ->assertJsonPath('data.rating_rata', 4)
            ->assertJsonPath('data.jumlah_ulasan', 2);
    }

    public function test_ulasan_terbaru_tersedia_untuk_halaman_depan(): void
    {
        Ulasan::factory()->count(3)->create(['komentar' => 'Nyaman dan dekat kampus.']);
        Ulasan::factory()->create(['komentar' => null]);

        $this->getJson('/api/ulasan/terbaru')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_penulis_tidak_dapat_menghapus_ulasannya_sendiri(): void
    {
        $penulis = User::factory()->create();
        $ulasan = Ulasan::factory()->create(['penyewa_id' => $penulis->id]);

        $this->actingAs($penulis)
            ->deleteJson("/api/ulasan/{$ulasan->id}")
            ->assertForbidden();
    }

    public function test_admin_dapat_menghapus_ulasan(): void
    {
        $ulasan = Ulasan::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/ulasan/{$ulasan->id}")
            ->assertOk();

        $this->assertSame(0, Ulasan::count());
    }
}
