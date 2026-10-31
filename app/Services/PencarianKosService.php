<?php

namespace App\Services;

use App\Enums\TipeKos;
use App\Models\Kos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Pencarian kos berdasarkan jarak dari satu titik acuan.
 *
 * CARA KERJA, DAN ALASANNYA
 *
 * Pencarian dikerjakan dua tahap:
 *
 *   1. Kotak pembatas di basis data. Baris disaring dengan perbandingan
 *      lintang dan bujur biasa, sehingga indeks pada kolom latitude dan
 *      longitude benar-benar terpakai. Tahap ini hanya memakai operator
 *      pembandingan, tidak ada fungsi trigonometri sama sekali.
 *
 *   2. Haversine di PHP. Jarak sebenarnya dihitung untuk kandidat yang lolos
 *      tahap pertama, lalu disaring ulang dengan radius yang diminta.
 *
 * Mengapa Haversine tidak dijalankan di dalam SQL: fungsi RADIANS, SIN, COS,
 * dan ASIN tersedia di MySQL, tetapi pada SQLite baru ada bila pustakanya
 * dikompilasi dengan SQLITE_ENABLE_MATH_FUNCTIONS. Seluruh pengujian aplikasi
 * ini berjalan di atas SQLite in-memory. Menaruh Haversine di SQL berarti
 * fitur terpenting sistem tidak akan pernah teruji otomatis, dan baru ketahuan
 * rusak saat demo.
 *
 * Kotak pembatas selalu lebih luas daripada lingkaran radius, sehingga tahap
 * pertama tidak pernah membuang kos yang seharusnya masuk. Penyaringan akhir
 * tetap memakai jarak Haversine yang sebenarnya, bukan sisi kotak.
 *
 * BATAS PENDEKATAN INI
 *
 * Seluruh kandidat di dalam kotak dimuat ke memori sebelum diurutkan. Untuk
 * studi kasus ini wajar: SRS menyebut sekitar 100 kos dalam radius 1 km.
 * Bila suatu saat datanya mencapai puluhan ribu baris dalam satu kotak,
 * perhitungan jarak perlu dipindahkan ke SQL atau memakai indeks spasial
 * MySQL (tipe POINT dengan ST_Distance_Sphere).
 */
class PencarianKosService
{
    /**
     * Jari-jari rata-rata bumi dalam meter.
     */
    private const RADIUS_BUMI_METER = 6_371_000;

    /**
     * Panjang satu derajat lintang dalam meter. Nilainya praktis tetap di
     * seluruh permukaan bumi; yang berubah menurut lintang adalah bujur.
     */
    private const METER_PER_DERAJAT_LINTANG = 111_320;

    /**
     * @param  array{tipe_kos?: ?string, harga_min?: ?int, harga_maks?: ?int, q?: ?string}  $filter
     */
    public function cari(
        float $lat,
        float $lng,
        int $radiusMeter,
        array $filter = [],
        int $perHalaman = 15,
        int $halaman = 1,
    ): LengthAwarePaginator {
        $kandidat = $this->kueriKandidat($lat, $lng, $radiusMeter, $filter)->get();

        $hasil = $this->hitungDanSaringJarak($kandidat, $lat, $lng, $radiusMeter);

        return new LengthAwarePaginator(
            items: $hasil->forPage($halaman, $perHalaman)->values(),
            total: $hasil->count(),
            perPage: $perHalaman,
            currentPage: $halaman,
            options: ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * Tahap 1 — kotak pembatas, dikerjakan basis data.
     *
     * @param  array{tipe_kos?: ?string, harga_min?: ?int, harga_maks?: ?int, q?: ?string}  $filter
     * @return Builder<Kos>
     */
    private function kueriKandidat(float $lat, float $lng, int $radiusMeter, array $filter): Builder
    {
        $kotak = $this->kotakPembatas($lat, $lng, $radiusMeter);

        return Kos::query()
            ->tayang()
            ->with(['foto'])
            ->withAvg('ulasan', 'rating')
            ->withCount('ulasan')
            ->whereBetween('latitude', [$kotak['lat_min'], $kotak['lat_maks']])
            ->whereBetween('longitude', [$kotak['lng_min'], $kotak['lng_maks']])
            ->when(
                filled($filter['tipe_kos'] ?? null),
                fn (Builder $q) => $q->where('tipe_kos', $filter['tipe_kos']),
            )
            ->when(
                filled($filter['harga_min'] ?? null),
                fn (Builder $q) => $q->where('harga_per_bulan', '>=', $filter['harga_min']),
            )
            ->when(
                filled($filter['harga_maks'] ?? null),
                fn (Builder $q) => $q->where('harga_per_bulan', '<=', $filter['harga_maks']),
            )
            ->when(
                filled($filter['q'] ?? null),
                fn (Builder $q) => $q->where(function (Builder $cari) use ($filter) {
                    $kata = '%'.$filter['q'].'%';
                    $cari->where('nama_kos', 'like', $kata)
                        ->orWhere('alamat', 'like', $kata);
                }),
            );
    }

    /**
     * Tahap 2 — jarak Haversine, dikerjakan PHP.
     *
     * @param  Collection<int, Kos>  $kandidat
     * @return Collection<int, Kos>
     */
    private function hitungDanSaringJarak(Collection $kandidat, float $lat, float $lng, int $radiusMeter): Collection
    {
        return $kandidat
            ->each(function (Kos $kos) use ($lat, $lng): void {
                $kos->setAttribute('jarak_meter', (int) round(
                    $this->jarakHaversine($lat, $lng, (float) $kos->latitude, (float) $kos->longitude),
                ));
            })
            ->filter(fn (Kos $kos): bool => $kos->getAttribute('jarak_meter') <= $radiusMeter)
            ->sortBy(fn (Kos $kos): int => $kos->getAttribute('jarak_meter'))
            ->values();
    }

    /**
     * Batas lintang dan bujur sebuah kotak yang pasti memuat seluruh titik
     * dalam radius yang diminta.
     *
     * @return array{lat_min: float, lat_maks: float, lng_min: float, lng_maks: float}
     */
    public function kotakPembatas(float $lat, float $lng, int $radiusMeter): array
    {
        $deltaLat = $radiusMeter / self::METER_PER_DERAJAT_LINTANG;

        // Satu derajat bujur makin pendek mendekati kutub. Pembagi dijaga agar
        // tidak mendekati nol di lintang ekstrem, yang akan membuat kotak
        // melebar tak terhingga.
        $kosinus = max(cos(deg2rad($lat)), 0.01);
        $deltaLng = $radiusMeter / (self::METER_PER_DERAJAT_LINTANG * $kosinus);

        return [
            'lat_min' => $lat - $deltaLat,
            'lat_maks' => $lat + $deltaLat,
            'lng_min' => $lng - $deltaLng,
            'lng_maks' => $lng + $deltaLng,
        ];
    }

    /**
     * Jarak lingkaran besar antara dua titik, dalam meter.
     *
     * Varian asin dipakai, bukan acos, karena acos kehilangan ketelitian untuk
     * jarak pendek — justru rentang yang paling sering dipakai aplikasi ini.
     */
    public function jarakHaversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLng / 2) ** 2;

        return self::RADIUS_BUMI_METER * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Daftar tipe kos untuk pilihan filter di antarmuka.
     *
     * @return array<int, array{nilai: string, label: string}>
     */
    public function pilihanTipeKos(): array
    {
        return array_map(
            fn (TipeKos $tipe): array => ['nilai' => $tipe->value, 'label' => $tipe->label()],
            TipeKos::cases(),
        );
    }
}
