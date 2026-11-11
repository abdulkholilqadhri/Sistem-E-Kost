<?php

namespace Database\Seeders;

use App\Enums\StatusPemesanan;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\PengajuanPemilik;
use App\Models\Ulasan;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Data simulasi untuk pengembangan dan demo.
 *
 * SELURUH ISI SEEDER INI FIKTIF. Nama orang, nama kos, dan alamatnya
 * dibangkitkan Faker, bukan diambil dari kos atau orang sungguhan di Majene.
 * Batasan nomor 3 pada Project Charter memang menetapkan sistem diuji dengan
 * data simulasi; ini pelaksanaannya.
 *
 * Foto kos dibuat sendiri oleh seeder sebagai gambar berwarna polos, bukan
 * diunduh dari internet, sehingga tidak ada persoalan hak pakai gambar.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Membuat data simulasi E-Kost...');

        $admin = User::factory()->admin()->create([
            'name' => 'Admin E-Kost',
            'email' => 'admin@ekost.test',
        ]);

        $pemilikUtama = User::factory()->pemilik()->create([
            'name' => 'Hasan Pemilik',
            'email' => 'pemilik@ekost.test',
        ]);

        $penyewaUtama = User::factory()->create([
            'name' => 'Sitti Penyewa',
            'email' => 'penyewa@ekost.test',
        ]);

        $pemilikLain = User::factory()->pemilik()->count(3)->create();
        $penyewaLain = User::factory()->count(6)->create();

        // Kos milik pemilik utama, supaya akun demo langsung punya isi.
        $kosUtama = Kos::factory()->count(4)->create(['pemilik_id' => $pemilikUtama->id]);

        $kosLain = $pemilikLain->flatMap(
            fn (User $pemilik) => Kos::factory()->count(3)->create(['pemilik_id' => $pemilik->id]),
        );

        $semuaKos = $kosUtama->concat($kosLain);

        $this->buatFoto($semuaKos);
        $this->buatPemesanan($kosUtama, $penyewaUtama, $penyewaLain);

        PengajuanPemilik::factory()->count(2)->create([
            'user_id' => fn () => User::factory()->create()->id,
        ]);

        $this->command->info('Selesai. Akun demo (kata sandi: password):');
        $this->command->line("  admin    : {$admin->email}");
        $this->command->line("  pemilik  : {$pemilikUtama->email}");
        $this->command->line("  penyewa  : {$penyewaUtama->email}");
    }

    /**
     * @param  Collection<int, Kos>  $semuaKos
     */
    private function buatFoto($semuaKos): void
    {
        $sumber = (string) config('ekost.foto_simulasi');

        if ($sumber === 'tenun' && ! extension_loaded('gd')) {
            $this->command->warn('Ekstensi GD tidak aktif — foto kos dilewati.');

            return;
        }

        $disk = Storage::disk(config('ekost.disk_publik'));
        $keterangan = ['Tampak depan', 'Kamar tidur', 'Kamar mandi'];

        foreach ($semuaKos as $kos) {
            foreach (range(0, 2) as $urutan) {
                /*
                 * Dua jalur. Untuk 'tenun', gambarnya dibuat lalu disimpan ke
                 * disk dan path relatifnya yang dicatat. Untuk sumber dari
                 * internet, URL-nya langsung dicatat sebagai path_foto dan
                 * diteruskan apa adanya oleh FotoKosResource.
                 */
                $path = $sumber === 'tenun'
                    ? $this->simpanGambarTenun($disk, $kos, $urutan, $keterangan[$urutan])
                    : $this->alamatFotoSimulasi($sumber, $kos->id, $urutan);

                $kos->foto()->create([
                    'path_foto' => $path,
                    'keterangan' => $keterangan[$urutan],
                    'urutan' => $urutan,
                ]);
            }
        }

        if ($sumber === 'picsum') {
            $this->command->warn(
                'Foto kos memakai picsum.photos — dimuat peramban langsung dari internet. '
                .'Setel EKOST_FOTO_SIMULASI=ilustrasi bila demo berjalan tanpa koneksi.',
            );
        }
    }

    /**
     * Alamat foto simulasi.
     *
     * Untuk 'ilustrasi', berkasnya sudah ada di public/foto-contoh — tiga
     * adegan (tampak depan, kamar tidur, kamar mandi) dalam empat warna.
     * Adegannya mengikuti urutan foto, warnanya mengikuti id kos, sehingga
     * galeri satu kos tidak berisi tiga kamar tidur dan kartu-kartu di daftar
     * tidak semuanya berwarna sama.
     *
     * 'foto' memakai susunan yang sama, dari public/foto-contoh/foto. Semua
     * berlisensi CC0, sehingga boleh disimpan di repositori tanpa atribusi.
     * Sumbernya tetap dicatat agar asal-usulnya bisa diperiksa:
     *
     *   depan-1  https://www.rawpixel.com/image/8812187/apartment-building
     *   depan-2  https://www.rawpixel.com/image/3290785/free-photo-image-apartment-building-architecture-banister
     *   depan-3  https://www.rawpixel.com/image/3297614/free-photo-image-automobile-building-car
     *   depan-4  https://www.rawpixel.com/image/3297774/free-photo-image-buildings-cc0-creative-commons
     *   kamar-1  https://stocksnap.io/photo/apartment-bed-EGXWIV409M (Medhat Ayad)
     *   kamar-2  https://www.rawpixel.com/image/5927139/photo-image-public-domain-wood-room
     *   kamar-3  https://stocksnap.io/photo/bedroom-clean-OE0F9BHXJQ (Burst)
     *   kamar-4  https://stocksnap.io/photo/bedroom-bed-9R85GU3I2P (Mary Whitney)
     *   mandi-1  https://www.rawpixel.com/image/6042971/photo-image-public-domain-house-room
     *   mandi-2  https://www.rawpixel.com/image/6077486/dsc09616
     *   mandi-3  https://www.rawpixel.com/image/9676690/modern-house-interior
     *   mandi-4  https://www.rawpixel.com/image/6077484/dsc09337
     *
     * Untuk 'picsum', kuncinya ditahan pada nilai tetap, bukan acak setiap
     * permintaan. Tanpa itu, setiap muat ulang halaman mengganti seluruh foto
     * — dan kartu yang gambarnya berganti-ganti terlihat rusak, bukan hidup.
     */
    private function alamatFotoSimulasi(string $sumber, int $kosId, int $urutan): string
    {
        if ($sumber === 'picsum') {
            return 'https://picsum.photos/seed/ekost'.($kosId * 10 + $urutan).'/800/600';
        }

        $adegan = ['depan', 'kamar', 'mandi'][$urutan];
        $varian = ($kosId % 4) + 1;

        if ($sumber === 'foto') {
            return "/foto-contoh/foto/{$adegan}-{$varian}.jpg";
        }

        return "/foto-contoh/{$adegan}-{$varian}.svg";
    }

    /**
     * @param  Filesystem  $disk
     */
    private function simpanGambarTenun($disk, Kos $kos, int $urutan, string $keterangan): string
    {
        $path = "foto-kos/simulasi-{$kos->id}-{$urutan}.jpg";

        $disk->put($path, $this->gambarTenun($kos->nama_kos, $keterangan, $kos->id + $urutan));

        return $path;
    }

    /**
     * Membuat gambar bermotif kotak-kotak ala sarung tenun Mandar.
     *
     * Dibuat sendiri, bukan diunduh dari internet, karena foto kos sungguhan
     * dari hasil pencarian gambar membawa persoalan hak pakai dan privasi
     * pemiliknya. Motif ini juga jujur menyatakan dirinya sebagai data
     * simulasi, sehingga tidak ada yang keliru mengira kos ini nyata.
     */
    private function gambarTenun(string $namaKos, string $keterangan, int $benih): string
    {
        $lebar = 480;
        $tinggi = 360;

        // Empat kombinasi warna dasar supaya kartu-kartu tidak seragam.
        $palet = [
            [[31, 55, 102], [40, 70, 127]],   // indigo
            [[122, 34, 34], [158, 43, 43]],   // maroon
            [[31, 77, 61], [41, 99, 79]],     // hijau tua
            [[61, 48, 24], [92, 72, 36]],     // tanah
        ];

        [$dasar, $pita] = $palet[$benih % count($palet)];

        $gambar = imagecreatetruecolor($lebar, $tinggi);
        imagealphablending($gambar, true);

        imagefilledrectangle(
            $gambar, 0, 0, $lebar, $tinggi,
            imagecolorallocate($gambar, ...$dasar),
        );

        // Pita lebar menyilang, separuh tembus pandang supaya titik potongnya
        // menghasilkan warna yang lebih pekat — seperti anyaman sungguhan.
        $warnaPita = imagecolorallocatealpha($gambar, ...[...$pita, 55]);

        for ($x = 0; $x < $lebar; $x += 72) {
            imagefilledrectangle($gambar, $x, 0, $x + 28, $tinggi, $warnaPita);
        }

        for ($y = 0; $y < $tinggi; $y += 72) {
            imagefilledrectangle($gambar, 0, $y, $lebar, $y + 28, $warnaPita);
        }

        // Benang emas tipis, ciri khas sarung sutera Mandar.
        $emas = imagecolorallocatealpha($gambar, 200, 146, 42, 45);

        for ($x = 44; $x < $lebar; $x += 72) {
            imagefilledrectangle($gambar, $x, 0, $x + 2, $tinggi, $emas);
        }

        for ($y = 44; $y < $tinggi; $y += 72) {
            imagefilledrectangle($gambar, 0, $y, $lebar, $y + 2, $emas);
        }

        // Keterangan di bawah, di atas bidang gelap agar tetap terbaca.
        imagefilledrectangle(
            $gambar, 0, $tinggi - 58, $lebar, $tinggi,
            imagecolorallocatealpha($gambar, 10, 16, 30, 40),
        );

        $putih = imagecolorallocate($gambar, 255, 255, 255);
        $redup = imagecolorallocate($gambar, 198, 208, 224);

        imagestring($gambar, 5, 16, $tinggi - 48, substr($namaKos, 0, 40), $putih);
        imagestring($gambar, 3, 16, $tinggi - 28, strtoupper($keterangan).' - DATA SIMULASI', $redup);

        ob_start();
        imagejpeg($gambar, null, 82);
        $isi = (string) ob_get_clean();
        imagedestroy($gambar);

        return $isi;
    }

    /**
     * Pemesanan pada beberapa tahap sekaligus, supaya setiap layar punya
     * sesuatu untuk ditampilkan tanpa harus menjalani seluruh alur dari awal.
     *
     * @param  Collection<int, Kos>  $kosUtama
     * @param  Collection<int, User>  $penyewaLain
     */
    private function buatPemesanan($kosUtama, User $penyewaUtama, $penyewaLain): void
    {
        $kos = $kosUtama->values();

        Pemesanan::factory()->create([
            'kos_id' => $kos[0]->id,
            'penyewa_id' => $penyewaUtama->id,
            'status' => StatusPemesanan::MenungguKonfirmasi,
        ]);

        Pemesanan::factory()->menungguPembayaran()->create([
            'kos_id' => $kos[1]->id,
            'penyewa_id' => $penyewaLain[0]->id,
        ]);

        Pemesanan::factory()->aktif()->create([
            'kos_id' => $kos[2]->id,
            'penyewa_id' => $penyewaLain[1]->id,
        ]);

        // Pesanan yang sudah selesai tetapi belum diulas, supaya form menulis
        // ulasan terlihat saat masuk sebagai penyewa utama.
        Pemesanan::factory()->selesai()->create([
            'kos_id' => $kos[3]->id,
            'penyewa_id' => $penyewaUtama->id,
            'tanggal_selesai' => today()->subWeek(),
        ]);

        Pemesanan::factory()->ditolak()->create([
            'kos_id' => $kos[0]->id,
            'penyewa_id' => $penyewaLain[3]->id,
        ]);

        $this->buatUlasan($kos, $penyewaLain);
    }

    /**
     * Ulasan hanya boleh melekat pada pemesanan yang berstatus selesai, dan
     * satu pemesanan hanya satu ulasan. Seeder mengikuti aturan yang sama
     * dengan aplikasinya: setiap ulasan dibuatkan pemesanan selesai miliknya
     * sendiri, bukan ditempelkan sembarangan.
     *
     * @param  Collection<int, Kos>  $kosUtama
     * @param  Collection<int, User>  $penyewaLain
     */
    private function buatUlasan($kosUtama, $penyewaLain): void
    {
        $contoh = [
            [5, 'Kamarnya bersih, air lancar, dan pemiliknya gampang dihubungi. Jalan kaki ke kampus sekitar sepuluh menit.'],
            [4, 'Lokasinya dekat kampus dan harganya masuk akal. Sinyal WiFi agak lemah di kamar belakang.'],
            [3, 'Kamar sesuai foto, tapi parkir motor penuh kalau pulang malam.'],
            [5, 'Sudah dua semester di sini dan betah. Lingkungannya tenang untuk belajar.'],
            [4, 'Fasilitas sesuai yang ditulis. Dapur bersama bersih karena ada jadwal piket.'],
        ];

        $kos = $kosUtama->values();

        foreach ($contoh as $indeks => [$rating, $komentar]) {
            $pemesanan = Pemesanan::factory()->selesai()->create([
                'kos_id' => $kos[$indeks % $kos->count()]->id,
                'penyewa_id' => $penyewaLain[$indeks % $penyewaLain->count()]->id,
                'tanggal_selesai' => today()->subMonths($indeks + 1),
            ]);

            // Penugasan langsung, bukan create() dengan array: pemesanan_id,
            // kos_id, dan penyewa_id sengaja tidak termasuk atribut fillable
            // pada model Ulasan, sehingga array akan mengabaikannya dan
            // penyimpanannya gagal karena kolomnya NOT NULL.
            $ulasan = new Ulasan(['rating' => $rating, 'komentar' => $komentar]);
            $ulasan->pemesanan_id = $pemesanan->id;
            $ulasan->kos_id = $pemesanan->kos_id;
            $ulasan->penyewa_id = $pemesanan->penyewa_id;
            $ulasan->save();
        }
    }
}
