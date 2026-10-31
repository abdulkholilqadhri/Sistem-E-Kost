<?php

namespace App\Services;

use App\Enums\StatusKos;
use App\Enums\StatusPemesanan;
use App\Exceptions\PemesananException;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Alur pemesanan kos, termasuk pengelolaan stok kamar.
 *
 * KAPAN KAMAR BERKURANG
 *
 * Kamar berkurang saat pemilik MENERIMA pesanan, bukan saat penyewa
 * mengajukannya. Akibatnya beberapa pengajuan boleh mengantre untuk kamar yang
 * sama, dan pemilik yang memutuskan siapa yang dapat.
 *
 * Alternatifnya — mengurangi kamar sejak pengajuan — berarti satu orang iseng
 * dapat mengosongkan seluruh stok kos hanya dengan mengirim banyak pengajuan
 * yang tidak pernah dibayar.
 *
 * Kamar dikembalikan saat pesanan dibatalkan setelah diterima, dan saat masa
 * sewa berakhir.
 *
 * MENGAPA PAKAI lockForUpdate
 *
 * Tanpa kunci baris, dua pemilik kos (atau satu pemilik di dua tab) yang
 * menerima dua pesanan untuk kamar terakhir pada saat bersamaan akan sama-sama
 * membaca sisa_kamar = 1, sama-sama lolos pemeriksaan, lalu sama-sama
 * mengurangi — menyisakan -1 atau 0 dengan dua pesanan aktif untuk satu kamar.
 * lockForUpdate memaksa transaksi kedua menunggu sampai yang pertama selesai,
 * sehingga ia membaca angka yang sudah diperbarui dan pemeriksaannya gagal.
 *
 * Catatan pengujian: SQLite mengabaikan lockForUpdate, dan pengujian otomatis
 * di berkas ini tidak dapat membuktikan perilaku itu. Yang terbukti lewat test
 * adalah aturan bisnisnya; perlindungan terhadap balapan baru benar-benar
 * berlaku di atas MySQL/InnoDB, yang dipakai aplikasi ini saat berjalan.
 */
class PemesananService
{
    public function ajukan(
        User $penyewa,
        Kos $kos,
        CarbonInterface $tanggalMulai,
        int $durasiBulan,
    ): Pemesanan {
        if ($kos->status !== StatusKos::Aktif) {
            throw PemesananException::kosTidakTersedia();
        }

        if ($kos->sisa_kamar < 1) {
            throw PemesananException::kamarHabis();
        }

        if ($this->punyaPesananBerjalan($penyewa, $kos)) {
            throw PemesananException::sudahPunyaPesananAktif();
        }

        // Harga disalin ke pesanan, bukan dirujuk dari kos. Bila pemilik
        // menaikkan harga bulan depan, tagihan pesanan ini tidak ikut berubah.
        $harga = $kos->harga_per_bulan;

        $pemesanan = new Pemesanan([
            'tanggal_mulai' => $tanggalMulai->toDateString(),
            'durasi_bulan' => $durasiBulan,
            'tanggal_selesai' => $tanggalMulai->copy()->addMonths($durasiBulan)->toDateString(),
            'harga_per_bulan' => $harga,
            'total_harga' => $harga * $durasiBulan,
        ]);

        $pemesanan->kos_id = $kos->id;
        $pemesanan->penyewa_id = $penyewa->id;
        $pemesanan->status = StatusPemesanan::MenungguKonfirmasi;
        $pemesanan->save();

        return $pemesanan;
    }

    /**
     * Pemilik menerima pesanan. Satu kamar dipotong dari stok di sini.
     */
    public function terima(Pemesanan $pemesanan): Pemesanan
    {
        return DB::transaction(function () use ($pemesanan): Pemesanan {
            $kos = Kos::query()
                ->whereKey($pemesanan->kos_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Dibaca ulang setelah kunci didapat. Angka yang dibaca sebelum
            // transaksi dimulai tidak dapat dipercaya.
            if ($kos->sisa_kamar < 1) {
                throw PemesananException::kamarHabis();
            }

            $this->pindahkanStatus($pemesanan, StatusPemesanan::MenungguPembayaran);

            $pemesanan->dikonfirmasi_pada = now();
            $pemesanan->batas_pembayaran = now()->addHours((int) config('ekost.batas_pembayaran_jam'));
            $pemesanan->save();

            $kos->decrement('sisa_kamar');

            return $pemesanan;
        });
    }

    public function tolak(Pemesanan $pemesanan, string $catatan): Pemesanan
    {
        // Stok tidak berubah: kamar belum pernah dipotong untuk pesanan yang
        // masih menunggu konfirmasi.
        $this->pindahkanStatus($pemesanan, StatusPemesanan::Ditolak);

        $pemesanan->catatan_pemilik = $catatan;
        $pemesanan->save();

        return $pemesanan;
    }

    /**
     * Pembatalan oleh penyewa, atau oleh sistem saat batas pembayaran lewat.
     */
    public function batalkan(Pemesanan $pemesanan, ?string $catatan = null): Pemesanan
    {
        return DB::transaction(function () use ($pemesanan, $catatan): Pemesanan {
            $kembalikanKamar = $pemesanan->status->menahanKamar();

            $this->pindahkanStatus($pemesanan, StatusPemesanan::Dibatalkan);

            if ($catatan !== null) {
                $pemesanan->catatan_pemilik = $catatan;
            }

            $pemesanan->save();

            if ($kembalikanKamar) {
                $this->kembalikanKamar($pemesanan);
            }

            return $pemesanan;
        });
    }

    /**
     * Masa sewa berakhir. Kamar kembali ke stok.
     */
    public function selesaikan(Pemesanan $pemesanan): Pemesanan
    {
        return DB::transaction(function () use ($pemesanan): Pemesanan {
            $this->pindahkanStatus($pemesanan, StatusPemesanan::Selesai);
            $pemesanan->save();

            $this->kembalikanKamar($pemesanan);

            return $pemesanan;
        });
    }

    /**
     * Dipakai PembayaranService setelah bukti transfer disetujui.
     */
    public function aktifkan(Pemesanan $pemesanan): Pemesanan
    {
        $this->pindahkanStatus($pemesanan, StatusPemesanan::Aktif);
        $pemesanan->save();

        return $pemesanan;
    }

    /**
     * Dipakai PembayaranService saat bukti transfer ditolak. Pesanan kembali
     * menunggu pembayaran dengan tenggat baru, kamar tetap ditahan.
     */
    public function mintaPembayaranUlang(Pemesanan $pemesanan): Pemesanan
    {
        $this->pindahkanStatus($pemesanan, StatusPemesanan::MenungguPembayaran);

        $pemesanan->batas_pembayaran = now()->addHours((int) config('ekost.batas_pembayaran_jam'));
        $pemesanan->save();

        return $pemesanan;
    }

    /**
     * Dipakai PembayaranService saat bukti diunggah.
     */
    public function tandaiMenungguVerifikasi(Pemesanan $pemesanan): Pemesanan
    {
        $this->pindahkanStatus($pemesanan, StatusPemesanan::MenungguVerifikasi);
        $pemesanan->save();

        return $pemesanan;
    }

    public function punyaPesananBerjalan(User $penyewa, Kos $kos): bool
    {
        return Pemesanan::query()
            ->where('penyewa_id', $penyewa->id)
            ->where('kos_id', $kos->id)
            ->whereIn('status', [
                StatusPemesanan::MenungguKonfirmasi,
                StatusPemesanan::MenungguPembayaran,
                StatusPemesanan::MenungguVerifikasi,
                StatusPemesanan::Aktif,
            ])
            ->exists();
    }

    /**
     * Satu-satunya tempat status pemesanan berubah.
     *
     * Seluruh perpindahan diuji terhadap state machine di StatusPemesanan,
     * sehingga tidak ada jalan menaikkan pesanan langsung dari "menunggu
     * konfirmasi" menjadi "aktif" tanpa melewati pembayaran.
     */
    private function pindahkanStatus(Pemesanan $pemesanan, StatusPemesanan $tujuan): void
    {
        if (! $pemesanan->status->bolehPindahKe($tujuan)) {
            throw PemesananException::transisiTidakSah($pemesanan->status, $tujuan);
        }

        $pemesanan->status = $tujuan;
    }

    private function kembalikanKamar(Pemesanan $pemesanan): void
    {
        $kos = Kos::query()
            ->whereKey($pemesanan->kos_id)
            ->lockForUpdate()
            ->firstOrFail();

        // Dibatasi jumlah_kamar agar kesalahan di tempat lain tidak sampai
        // membuat stok melebihi kamar yang benar-benar ada.
        if ($kos->sisa_kamar < $kos->jumlah_kamar) {
            $kos->increment('sisa_kamar');
        }
    }
}
