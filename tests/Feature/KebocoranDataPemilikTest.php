<?php

namespace Tests\Feature;

use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Detail kos terbuka untuk umum dan memuat relasi pemilik.
 *
 * Selama relasi itu diserialisasi dengan UserResource, surel setiap pemilik
 * kos — beserta status akun dan alasan penonaktifannya — terbaca siapa pun
 * yang membuka halaman detail, tanpa perlu login. Test ini menjaga agar
 * UserResource tidak kembali dipakai di sana tanpa disadari.
 */
class KebocoranDataPemilikTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_kos_publik_tidak_membocorkan_surel_pemilik(): void
    {
        $pemilik = User::factory()->pemilik()->create([
            'email' => 'pemilik.rahasia@contoh.test',
            'no_hp' => '081234567890',
        ]);

        $kos = Kos::factory()->create(['pemilik_id' => $pemilik->id]);

        $jawaban = $this->getJson("/api/kos/{$kos->id}")->assertOk();

        $jawaban->assertJsonPath('data.pemilik.nama', $pemilik->name);
        $jawaban->assertJsonMissingPath('data.pemilik.email');
        $jawaban->assertJsonMissingPath('data.pemilik.no_hp');
        $jawaban->assertJsonMissingPath('data.pemilik.status_akun');
        $jawaban->assertJsonMissingPath('data.pemilik.alasan_nonaktif');

        // Penjagaan terakhir: surelnya tidak boleh muncul di mana pun dalam
        // badan jawaban, termasuk di kunci yang belum terpikirkan.
        $this->assertStringNotContainsString('pemilik.rahasia@contoh.test', $jawaban->getContent());
    }

    public function test_penyewa_pemilik_pesanan_tetap_menerima_kontak_pemilik(): void
    {
        $pemilik = User::factory()->pemilik()->create(['no_hp' => '081234567890']);
        $penyewa = User::factory()->create();
        $kos = Kos::factory()->create(['pemilik_id' => $pemilik->id]);

        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create([
            'penyewa_id' => $penyewa->id,
            'kos_id' => $kos->id,
        ]);

        $this->actingAs($penyewa)
            ->getJson("/api/pemesanan/{$pemesanan->id}")
            ->assertOk()
            ->assertJsonPath('data.kontak_pemilik.nama', $pemilik->name)
            ->assertJsonPath('data.kontak_pemilik.no_hp', '081234567890');
    }

    public function test_orang_lain_tidak_dapat_membuka_pesanan_yang_bukan_miliknya(): void
    {
        $pemesanan = Pemesanan::factory()->menungguPembayaran()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/pemesanan/{$pemesanan->id}")
            ->assertForbidden();
    }
}
