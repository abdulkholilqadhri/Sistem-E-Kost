<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#16233f">
    <title>{{ config('app.name') }}</title>

    <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/favicon-96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    {{--
        Huruf dimuat langsung dari Bunny Fonts, bukan lewat opsi fonts pada
        laravel-vite-plugin. Opsi itu tidak menghasilkan @font-face apa pun
        pada proyek ini, dan akibatnya seluruh halaman jatuh ke huruf bawaan
        sistem tanpa satu pun pesan kesalahan.
    --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        rel="stylesheet"
        href="https://fonts.bunny.net/css?family=bricolage-grotesque:600,800|instrument-sans:400,500,600&display=swap"
    >

    {{--
        Gaya pramuat ditulis sebaris di sini, bukan di resources/css/app.css.

        Berkas CSS aplikasi diunduh Vite bersama bundelnya; selama unduhan itu
        berlangsung, aturan apa pun di dalamnya belum berlaku. Pramuat justru
        harus tampil tepat pada rentang waktu tersebut, sehingga gayanya harus
        sudah ada di dalam dokumen HTML yang pertama kali tiba.

        Alasan yang sama berlaku untuk hurufnya: pramuat memakai huruf bawaan
        sistem, karena Bricolage Grotesque belum tentu selesai diunduh.
    --}}
    <style>
        #pramuat {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: grid;
            place-items: center;
            background: #16233f;
            color: #fff;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            opacity: 1;
            transition: opacity 420ms ease;
        }

        #pramuat.selesai { opacity: 0; pointer-events: none; }

        .pramuat-isi { text-align: center; }

        /* Riak melingkar yang memancar dari satu titik — bentuk yang sama
           dengan lingkaran radius pencarian di peta. Gerakannya menyatakan
           apa yang dikerjakan aplikasi ini, bukan sekadar memutar. */
        .pramuat-riak {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .pramuat-riak span {
            position: absolute;
            inset: 0;
            margin: auto;
            width: 96px;
            height: 96px;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-radius: 50%;
            animation: pramuat-riak 2.2s cubic-bezier(0.22, 0.6, 0.3, 1) infinite;
        }

        .pramuat-riak span:nth-child(2) { animation-delay: 0.73s; }
        .pramuat-riak span:nth-child(3) { animation-delay: 1.46s; }

        /*
         * Logo berdiri di atas bidang putih berbentuk lingkaran.
         *
         * Lambangnya memakai biru tua yang nyaris sama dengan latar pramuat,
         * dan berkas PNG-nya berlatar tembus pandang — ditaruh langsung di
         * atas latar gelap, rumahnya akan lenyap. Bidang putih ini yang
         * membuatnya terbaca, sekaligus menjadi pusat tempat riak memancar.
         */
        .pramuat-lambang {
            position: absolute;
            inset: 0;
            margin: auto;
            width: 88px;
            height: 88px;
            display: grid;
            place-items: center;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .pramuat-lambang img {
            width: 56px;
            height: 56px;
            display: block;
        }

        @keyframes pramuat-riak {
            0%   { transform: scale(1); opacity: 0; }
            12%  { opacity: 0.8; }
            100% { transform: scale(2.1); opacity: 0; }
        }

        .pramuat-nama {
            margin: 1.5rem 0 0;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .pramuat-ket {
            margin: 0.4rem 0 0;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
        }

        @media (prefers-reduced-motion: reduce) {
            .pramuat-riak span {
                animation: none;
                transform: scale(1.3);
                opacity: 0.35;
            }

            .pramuat-riak span:nth-child(2) { transform: scale(1.6); opacity: 0.22; }
            .pramuat-riak span:nth-child(3) { transform: scale(2.1); opacity: 0.12; }
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-900 antialiased">
    <div id="pramuat" role="status" aria-label="Memuat E-Kost">
        <div class="pramuat-isi">
            <div class="pramuat-riak" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
                <div class="pramuat-lambang">
                    <img src="/logo-pramuat.png" width="56" height="56" alt="">
                </div>
            </div>
            <p class="pramuat-nama">E&#8209;Kost</p>
            <p class="pramuat-ket">Menyiapkan peta kos</p>
        </div>
    </div>

    <div id="app"></div>

    {{--
        Titik acuan pencarian ditanam di halaman, bukan diambil lewat
        permintaan API tersendiri, supaya peta dapat langsung terbuka pada
        posisi yang benar tanpa menunggu satu putaran jaringan.

        Nilainya disusun di routes/web.php. Lat dan lng bisa null bila
        EKOST_KAMPUS_LAT/LNG belum diisi di .env.
    --}}
    <script>
        window.EKOST = @json($ekost);
    </script>

    <script>
        /*
         * Pengendali pramuat.
         *
         * Ditulis sebaris dan tanpa kerangka kerja apa pun karena ia harus
         * berjalan sebelum bundel aplikasi selesai diunduh — yaitu persis
         * pada rentang waktu yang hendak diisi pramuat ini.
         */
        (function () {
            /*
             * Berapa lama pramuat ditahan di layar, dalam milidetik.
             *
             * Aplikasinya hampir selalu siap lebih cepat dari ini. Angka ini
             * menahan halaman dengan sengaja supaya animasinya sempat
             * terlihat utuh — keputusan tampilan, bukan kebutuhan teknis.
             * Menurunkannya ke 0 membuat pramuat menutup segera setelah
             * halaman siap, dan itu yang paling cepat bagi pengguna.
             */
            var DURASI_MINIMUM_MS = 3000;

            var el = document.getElementById('pramuat');

            /*
             * Penanda bahwa layar sudah bebas dari pramuat.
             *
             * Animasi masuk tiap komponen (resources/js/animasi.js) menunggu
             * penanda ini. Tanpa penundaan itu, animasinya berjalan di balik
             * pramuat dan sudah selesai sebelum pengguna melihat halamannya —
             * yang tampak hanyalah halaman yang langsung diam.
             */
            function tandaiSelesai() {
                document.documentElement.setAttribute('data-pramuat', 'selesai');
                window.dispatchEvent(new Event('pramuat-selesai'));
            }

            if (!el) {
                tandaiSelesai();

                return;
            }

            var mulai = Date.now();

            /*
             * Jaring pengaman. Bila bundel gagal diunduh atau melempar galat
             * saat dijalankan, tutupPramuat tidak akan pernah dipanggil dan
             * pramuat akan menutupi halaman selamanya. Lewat sepuluh detik,
             * ia menyingkir dengan sendirinya — pengguna setidaknya melihat
             * apa yang sempat termuat, bukan layar biru tanpa penjelasan.
             */
            var batasWaktu = setTimeout(function () {
                tutup();
            }, 10000);

            function tutup() {
                clearTimeout(batasWaktu);

                el.classList.add('selesai');
                tandaiSelesai();

                // Dihapus dari DOM setelah peluruhannya selesai, agar tidak
                // meninggalkan lapisan tak terlihat di atas seluruh halaman.
                setTimeout(function () {
                    el.remove();
                }, 460);

                window.tutupPramuat = function () {};
            }

            window.tutupPramuat = function () {
                /*
                 * Aplikasi sudah siap. Sisa waktu dihitung dari saat halaman
                 * mulai dimuat, bukan dari saat ini — sehingga pemuatan yang
                 * lambat tidak menambah tiga detik lagi di atas waktu tunggu
                 * yang sudah panjang.
                 */
                var sisa = DURASI_MINIMUM_MS - (Date.now() - mulai);

                if (sisa > 0) {
                    setTimeout(tutup, sisa);

                    return;
                }

                tutup();
            };
        })();
    </script>
</body>
</html>
