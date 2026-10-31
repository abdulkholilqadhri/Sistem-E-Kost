<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Titik Acuan Pencarian
    |--------------------------------------------------------------------------
    |
    | Koordinat kampus dipakai sebagai titik acuan bawaan saat pengguna belum
    | mengizinkan akses lokasi browser, dan sebagai pusat peta pertama kali.
    | Nilainya wajib diisi di .env; tanpa itu pencarian berbasis jarak tidak
    | akan memberi hasil yang benar.
    |
    */

    'kampus' => [
        'nama' => env('EKOST_KAMPUS_NAMA', 'Universitas Sulawesi Barat'),
        'lat' => env('EKOST_KAMPUS_LAT') !== null ? (float) env('EKOST_KAMPUS_LAT') : null,
        'lng' => env('EKOST_KAMPUS_LNG') !== null ? (float) env('EKOST_KAMPUS_LNG') : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pencarian
    |--------------------------------------------------------------------------
    |
    | radius_default dan radius_maksimum dalam meter. Batas atas dipasang agar
    | satu permintaan tidak memaksa basis data menghitung Haversine terhadap
    | seluruh baris tabel kos.
    |
    */

    'radius_default' => (int) env('EKOST_RADIUS_DEFAULT', 1000),
    'radius_maksimum' => (int) env('EKOST_RADIUS_MAKSIMUM', 10000),

    /*
    |--------------------------------------------------------------------------
    | Pemesanan
    |--------------------------------------------------------------------------
    |
    | Batas waktu unggah bukti bayar setelah pemilik menerima pesanan. Lewat
    | dari ini, pesanan dibatalkan dan kamar dikembalikan ke stok.
    |
    */

    'batas_pembayaran_jam' => (int) env('EKOST_BATAS_PEMBAYARAN_JAM', 24),

    'durasi_sewa_maksimum_bulan' => (int) env('EKOST_DURASI_MAKSIMUM_BULAN', 12),

    /*
    |--------------------------------------------------------------------------
    | Unggahan Berkas
    |--------------------------------------------------------------------------
    |
    | Berkas identitas dan bukti transfer disimpan pada disk privat, bukan pada
    | direktori publik, karena memuat data pribadi (NIK, nomor rekening).
    | Foto kos boleh publik.
    |
    */

    'disk_privat' => 'local',
    'disk_publik' => 'public',

    'ukuran_maksimum_kb' => [
        'foto_kos' => 2048,
        'dokumen' => 4096,
        'bukti_bayar' => 2048,
    ],

    'maksimum_foto_per_kos' => (int) env('EKOST_MAKSIMUM_FOTO_PER_KOS', 12),

    /*
    |--------------------------------------------------------------------------
    | Layanan Peta Eksternal
    |--------------------------------------------------------------------------
    |
    | Keduanya server publik dengan kebijakan pemakaian yang mengikat:
    | Nominatim membatasi satu permintaan per detik dan mewajibkan User-Agent
    | yang mengidentifikasi aplikasi; OSRM demo server tidak diperuntukkan bagi
    | produksi. Bila layanan ini gagal, sistem harus tetap menyajikan daftar
    | kos tanpa rute (NFR-04.2), bukan menampilkan halaman error.
    |
    */

    'osrm' => [
        'base_url' => rtrim((string) env('OSRM_BASE_URL', 'https://router.project-osrm.org'), '/'),
        'timeout' => (int) env('OSRM_TIMEOUT', 8),
    ],

    'nominatim' => [
        'base_url' => rtrim((string) env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'), '/'),
        'user_agent' => (string) env('NOMINATIM_USER_AGENT', 'E-Kost'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 8),
    ],

];
