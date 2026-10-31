<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mencari koordinat dari nama tempat atau alamat, lewat Nominatim.
 *
 * Dipakai pemilik kos untuk menemukan lokasi kosnya tanpa harus menggeser
 * peta dari titik kampus sampai ketemu.
 *
 * KEBIJAKAN PEMAKAIAN NOMINATIM
 *
 * Server publik Nominatim mensyaratkan: maksimal satu permintaan per detik,
 * User-Agent yang mengidentifikasi aplikasi, dan tidak boleh dipakai untuk
 * beban berat. Melanggarnya berarti alamat IP diblokir — dan yang terblokir
 * adalah server aplikasi, bukan satu pengguna.
 *
 * Karena itu pemanggilannya dilakukan dari sisi server, bukan langsung dari
 * peramban: hanya di sini User-Agent dapat dipastikan terkirim, hasilnya
 * dapat disimpan di cache, dan jumlah permintaannya dapat dibatasi.
 */
class GeocodingService
{
    private const DURASI_CACHE_DETIK = 86400;

    /**
     * Seberapa luas wilayah yang diprioritaskan di sekitar titik acuan,
     * dalam derajat. 0,5 derajat kurang lebih 55 km.
     */
    private const LEBAR_PRIORITAS_DERAJAT = 0.5;

    /**
     * @return array<int, array{nama: string, lat: float, lng: float}>
     *                                                                 Daftar kosong bila layanan tidak dapat dihubungi; pemanggil
     *                                                                 tetap dapat menaruh penanda dengan mengklik peta.
     */
    public function cari(string $kata): array
    {
        $kata = trim($kata);

        if (mb_strlen($kata) < 3) {
            return [];
        }

        return Cache::remember(
            'geocode:'.md5(mb_strtolower($kata)),
            self::DURASI_CACHE_DETIK,
            fn () => $this->ambilDariNominatim($kata),
        );
    }

    /**
     * @return array<int, array{nama: string, lat: float, lng: float}>
     */
    private function ambilDariNominatim(string $kata): array
    {
        $parameter = [
            'q' => $kata,
            'format' => 'jsonv2',
            'limit' => 6,
            'countrycodes' => 'id',
        ];

        // Hasil di sekitar kampus diprioritaskan, tetapi tidak dibatasi hanya
        // ke wilayah itu: pemilik kos boleh saja mendaftarkan kos yang agak
        // jauh, dan membatasi keras akan membuat pencariannya tampak rusak.
        $lat = config('ekost.kampus.lat');
        $lng = config('ekost.kampus.lng');

        if ($lat !== null && $lng !== null) {
            $sisi = self::LEBAR_PRIORITAS_DERAJAT;
            $parameter['viewbox'] = implode(',', [
                $lng - $sisi, $lat + $sisi, $lng + $sisi, $lat - $sisi,
            ]);
            $parameter['bounded'] = 0;
        }

        try {
            $response = Http::timeout((int) config('ekost.nominatim.timeout'))
                ->withHeaders(['User-Agent' => config('ekost.nominatim.user_agent')])
                ->get(config('ekost.nominatim.base_url').'/search', $parameter);
        } catch (Throwable $e) {
            Log::warning('Layanan pencarian alamat tidak dapat dihubungi.', [
                'pesan' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Layanan pencarian alamat menolak permintaan.', [
                'status' => $response->status(),
            ]);

            return [];
        }

        return collect($response->json() ?? [])
            ->map(fn (array $hasil): array => [
                'nama' => $hasil['display_name'] ?? '',
                'lat' => (float) ($hasil['lat'] ?? 0),
                'lng' => (float) ($hasil['lon'] ?? 0),
            ])
            ->filter(fn (array $hasil): bool => $hasil['nama'] !== '')
            ->values()
            ->all();
    }
}
