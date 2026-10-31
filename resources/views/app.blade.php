<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>

    {{--
        Huruf dimuat langsung dari Bunny Fonts, bukan lewat opsi fonts pada
        laravel-vite-plugin. Opsi itu tidak menghasilkan @font-face apa pun
        pada proyek ini, dan akibatnya seluruh halaman jatuh ke huruf bawaan
        sistem tanpa satu pun pesan kesalahan.

        preconnect dipasang supaya permintaan hurufnya tidak menunggu
        penyambungan TLS setelah CSS selesai diurai.
    --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        rel="stylesheet"
        href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,800|instrument-sans:400,500,600&display=swap"
    >

    {{--
        Titik acuan pencarian ditanam di halaman, bukan diambil lewat
        permintaan API tersendiri, supaya peta dapat langsung terbuka pada
        posisi yang benar tanpa menunggu satu putaran jaringan.

        Nilainya disusun di routes/web.php. Lat dan lng bisa null bila
        EKOST_KAMPUS_LAT/LNG belum diisi di .env; antarmuka menangani keadaan
        itu, bukan menganggapnya mustahil.

        @json dipakai, bukan json_encode mentah, karena directive ini memasang
        flag JSON_HEX_TAG dan kawan-kawannya — sehingga nilai yang memuat
        </script> tidak dapat keluar dari blok skrip ini.
    --}}
    <script>
        window.EKOST = @json($ekost);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">
    <div id="app"></div>
</body>
</html>
