<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rute perjalanan dari titik acuan ke sebuah kos, lewat OSRM (FR-06).
 *
 * DUA ANGKA JARAK YANG BERBEDA
 *
 * PencarianKosService memberi jarak garis lurus (Haversine); service ini
 * memberi jarak tempuh lewat jalan, yang selalu lebih besar. Keduanya benar
 * untuk maksudnya masing-masing, tetapi WAJIB diberi label berbeda di
 * antarmuka. Mockup pada SDD menampilkan "350m dari Kampus" dan "400M KE
 * KAMPUS" tanpa menjelaskan yang mana — persis kebingungan yang harus
 * dihindari.
 *
 * KEGAGALAN LAYANAN BUKAN KEGAGALAN SISTEM
 *
 * OSRM yang dipakai adalah server demo publik: bukan untuk produksi, bisa
 * lambat, bisa menolak, bisa mati. Karena itu seluruh kegagalan di sini
 * dikembalikan sebagai null, bukan dilempar sebagai pengecualian. Daftar kos
 * dan peta harus tetap tampil tanpa rute (NFR-04.2); yang hilang hanya garis
 * rutenya.
 *
 * HASIL DISIMPAN SEMENTARA
 *
 * Rute antara dua titik yang sama tidak berubah dari menit ke menit, sedangkan
 * layanan publik membatasi pemakaian. Hasilnya disimpan di cache dengan kunci
 * berupa koordinat yang dibulatkan, sehingga pengguna yang bergeser beberapa
 * meter tidak memicu permintaan baru.
 */
class RuteService
{
    private const DURASI_CACHE_DETIK = 3600;

    /**
     * Pembulatan koordinat untuk kunci cache. Lima angka di belakang koma
     * kurang lebih satu meter — cukup halus untuk rute, cukup kasar untuk
     * menyatukan permintaan dari titik yang praktis sama.
     */
    private const PRESISI_KUNCI = 5;

    /**
     * @return array{jarak_meter: int, durasi_detik: int, geometri: array<string, mixed>}|null
     *                                                                                         null bila layanan rute tidak dapat dihubungi atau tidak
     *                                                                                         menemukan rute.
     */
    public function rute(float $dariLat, float $dariLng, float $keLat, float $keLng): ?array
    {
        $kunci = $this->kunciCache($dariLat, $dariLng, $keLat, $keLng);

        return Cache::remember(
            $kunci,
            self::DURASI_CACHE_DETIK,
            fn () => $this->ambilDariOsrm($dariLat, $dariLng, $keLat, $keLng),
        );
    }

    /**
     * @return array{jarak_meter: int, durasi_detik: int, geometri: array<string, mixed>}|null
     */
    private function ambilDariOsrm(float $dariLat, float $dariLng, float $keLat, float $keLng): ?array
    {
        // OSRM menerima koordinat dengan urutan bujur,lintang — kebalikan dari
        // urutan yang dipakai Leaflet dan sebagian besar basis data. Tertukar
        // di sini akan menghasilkan rute di belahan bumi yang salah tanpa
        // pesan kesalahan apa pun.
        $koordinat = sprintf('%F,%F;%F,%F', $dariLng, $dariLat, $keLng, $keLat);

        $url = config('ekost.osrm.base_url')."/route/v1/driving/{$koordinat}";

        try {
            $response = Http::timeout((int) config('ekost.osrm.timeout'))
                ->withHeaders(['User-Agent' => config('ekost.nominatim.user_agent')])
                ->get($url, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                ]);
        } catch (Throwable $e) {
            Log::warning('Layanan rute tidak dapat dihubungi.', ['pesan' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Layanan rute menolak permintaan.', ['status' => $response->status()]);

            return null;
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 'Ok' || blank($data['routes'] ?? null)) {
            return null;
        }

        $rute = $data['routes'][0];

        return [
            'jarak_meter' => (int) round($rute['distance'] ?? 0),
            'durasi_detik' => (int) round($rute['duration'] ?? 0),
            'geometri' => $rute['geometry'] ?? [],
        ];
    }

    private function kunciCache(float $dariLat, float $dariLng, float $keLat, float $keLng): string
    {
        return 'rute:'.implode(',', [
            round($dariLat, self::PRESISI_KUNCI),
            round($dariLng, self::PRESISI_KUNCI),
            round($keLat, self::PRESISI_KUNCI),
            round($keLng, self::PRESISI_KUNCI),
        ]);
    }
}
