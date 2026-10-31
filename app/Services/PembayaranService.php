<?php

namespace App\Services;

use App\Enums\StatusPembayaran;
use App\Enums\StatusPemesanan;
use App\Exceptions\PemesananException;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Unggah dan verifikasi bukti pembayaran (FR-12 dan FR-13).
 *
 * Bukti transfer memuat nomor rekening dan nama pengirim, sehingga disimpan
 * pada disk privat dan hanya dapat dibuka lewat controller yang memeriksa
 * PembayaranPolicy. Path-nya tidak pernah ikut dalam respons API.
 *
 * Catatan terhadap mockup SDD: mockup menampilkan "Rekening Escrow E-Kost",
 * yang berarti uang masuk ke rekening platform. Itu bertentangan dengan
 * batasan nomor 6 pada Project Charter — sistem tidak mencakup transaksi
 * pembayaran langsung. Yang diterapkan di sini adalah transfer langsung ke
 * pemilik kos, dengan bukti yang diverifikasi pemilik. Karena itu pula
 * verifikasi menjadi wewenang pemilik, bukan admin.
 */
class PembayaranService
{
    private const FOLDER = 'bukti-bayar';

    public function __construct(private readonly PemesananService $pemesananService) {}

    public function unggahBukti(
        Pemesanan $pemesanan,
        UploadedFile $bukti,
        int $jumlahBayar,
        ?string $namaPengirim,
        ?CarbonInterface $tanggalTransfer,
    ): Pembayaran {
        if ($pemesanan->status !== StatusPemesanan::MenungguPembayaran) {
            throw PemesananException::belumWaktunyaMembayar();
        }

        $disk = config('ekost.disk_privat');
        $path = $bukti->store(self::FOLDER, $disk);

        if ($path === false) {
            throw new RuntimeException('Bukti pembayaran gagal disimpan.');
        }

        return DB::transaction(function () use ($pemesanan, $path, $disk, $jumlahBayar, $namaPengirim, $tanggalTransfer): Pembayaran {
            $pembayaran = $pemesanan->pembayaran;

            if ($pembayaran !== null) {
                // Unggahan ulang setelah bukti sebelumnya ditolak. Berkas lama
                // dihapus supaya tidak menumpuk dokumen berisi nomor rekening
                // yang tidak lagi dipakai.
                Storage::disk($disk)->delete($pembayaran->path_bukti);
            } else {
                $pembayaran = new Pembayaran;
                $pembayaran->pemesanan_id = $pemesanan->id;
            }

            $pembayaran->fill([
                'jumlah_bayar' => $jumlahBayar,
                'path_bukti' => $path,
                'nama_pengirim' => $namaPengirim,
                'tanggal_transfer' => $tanggalTransfer?->toDateString(),
            ]);

            $pembayaran->status = StatusPembayaran::Menunggu;
            $pembayaran->catatan = null;
            $pembayaran->diverifikasi_oleh = null;
            $pembayaran->diverifikasi_pada = null;
            $pembayaran->save();

            $this->pemesananService->tandaiMenungguVerifikasi($pemesanan);

            return $pembayaran;
        });
    }

    /**
     * Pemilik menyetujui bukti. Pesanan menjadi aktif.
     */
    public function setujui(Pembayaran $pembayaran, User $pemilik, ?string $catatan = null): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $pemilik, $catatan): Pembayaran {
            $pembayaran->status = StatusPembayaran::Terverifikasi;
            $pembayaran->catatan = $catatan;
            $pembayaran->diverifikasi_oleh = $pemilik->id;
            $pembayaran->diverifikasi_pada = now();
            $pembayaran->save();

            $this->pemesananService->aktifkan($pembayaran->pemesanan);

            return $pembayaran;
        });
    }

    /**
     * Pemilik menolak bukti. Pesanan kembali menunggu pembayaran dengan
     * tenggat baru; kamar tetap ditahan supaya penyewa punya kesempatan
     * memperbaiki buktinya tanpa kehilangan kamar.
     */
    public function tolak(Pembayaran $pembayaran, User $pemilik, string $catatan): Pembayaran
    {
        return DB::transaction(function () use ($pembayaran, $pemilik, $catatan): Pembayaran {
            $pembayaran->status = StatusPembayaran::Ditolak;
            $pembayaran->catatan = $catatan;
            $pembayaran->diverifikasi_oleh = $pemilik->id;
            $pembayaran->diverifikasi_pada = now();
            $pembayaran->save();

            $this->pemesananService->mintaPembayaranUlang($pembayaran->pemesanan);

            return $pembayaran;
        });
    }
}
