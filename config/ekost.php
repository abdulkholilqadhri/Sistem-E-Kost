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
    | filled() dipakai, bukan pembandingan dengan null: baris "EKOST_KAMPUS_LAT="
    | yang kosong di .env menghasilkan string kosong, dan (float) '' adalah 0.
    | Koordinat 0,0 berada di Teluk Guinea, sehingga pencarian akan tetap
    | berjalan tetapi seluruh jaraknya salah tanpa satu pun pesan kesalahan.
    | Dibiarkan null, kegagalan itu terlihat sejak awal.
    |
    */

    'kampus' => [
        'nama' => env('EKOST_KAMPUS_NAMA', 'Universitas Sulawesi Barat'),
        'lat' => filled(env('EKOST_KAMPUS_LAT')) ? (float) env('EKOST_KAMPUS_LAT') : null,
        'lng' => filled(env('EKOST_KAMPUS_LNG')) ? (float) env('EKOST_KAMPUS_LNG') : null,
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
    | Daftar Fasilitas
    |--------------------------------------------------------------------------
    |
    | Satu daftar baku untuk dua tempat: pilihan centang saat pemilik
    | mendaftarkan kos, dan penyaring pada halaman pencarian.
    |
    | Harus satu sumber. Bila daftarnya ditulis terpisah di formulir dan di
    | penyaring, satu perbedaan ejaan saja sudah cukup membuat penyaring tidak
    | pernah menemukan kos mana pun — "KM Dalam" pada penyaring tidak akan
    | cocok dengan "Kamar Mandi Dalam" yang tersimpan di basis data. Kegagalan
    | semacam itu tidak memunculkan galat apa pun, hanya hasil kosong yang
    | tampak seperti memang tidak ada kos yang cocok.
    |
    */

    'fasilitas' => [
        'WiFi',
        'Kamar Mandi Dalam',
        'AC',
        'Kasur',
        'Lemari',
        'Meja Belajar',
        'Dapur Bersama',
        'Parkir Motor',
        'Air Panas',
        'Laundry',
    ],

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
    | Sumber Foto Simulasi (hanya untuk seeder)
    |--------------------------------------------------------------------------
    |
    | Menentukan gambar apa yang dipasang seeder pada kos contoh. Tidak
    | berpengaruh sama sekali pada foto yang diunggah pemilik kos sungguhan.
    |
    |   'foto'      — foto berlisensi CC0 dari public/foto-contoh/foto. Tampak
    |                 depan, kamar tidur, dan kamar mandi, empat varian
    |                 masing-masing. Diunduh sekali lewat Openverse dari
    |                 StockSnap dan rawpixel lalu disimpan di proyek, jadi
    |                 sama amannya dengan ilustrasi saat tidak ada koneksi.
    |                 Sumber tiap berkas dicatat di DatabaseSeeder.
    |   'ilustrasi' — ilustrasi ruangan dari public/foto-contoh. Tampak depan,
    |                 kamar tidur, dan kamar mandi, dalam empat warna. Berkasnya
    |                 ada di dalam proyek, sehingga tidak pernah gagal dimuat
    |                 dan tidak bergantung pada koneksi apa pun.
    |   'picsum'    — foto acak dari picsum.photos, dimuat peramban langsung
    |                 dari internet. Subjeknya bukan kamar kos, dan seluruh
    |                 kartu kehilangan gambarnya bila koneksi bermasalah.
    |   'tenun'     — gambar bermotif sarung Mandar yang dibuat seeder memakai
    |                 GD. Datar, tetapi memuat nama kos dan penanda "DATA
    |                 SIMULASI" di atasnya.
    |
    | Bawaannya 'foto'. Untuk sidang, sumber yang tersimpan di proyek adalah
    | yang paling aman: gambar yang gagal dimuat meninggalkan kartu kosong di
    | seluruh halaman, dan itu terjadi justru pada saat yang paling tidak
    | tepat. Foto sungguhan dipilih di atas ilustrasi karena kartu kos lebih
    | mudah dinilai penguji bila tampak seperti iklan kos yang sebenarnya.
    |
    */

    'foto_simulasi' => env('EKOST_FOTO_SIMULASI', 'foto'),

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
