<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PemesananException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pembayaran\UnggahBuktiRequest;
use App\Http\Requests\Pembayaran\VerifikasiPembayaranRequest;
use App\Http\Resources\PembayaranResource;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Services\PembayaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FR-12 (unggah bukti pembayaran) dan FR-13 (verifikasi pembayaran).
 */
class PembayaranController extends Controller
{
    public function __construct(private readonly PembayaranService $service) {}

    public function store(UnggahBuktiRequest $request, Pemesanan $pemesanan): JsonResponse
    {
        $this->authorize('bayar', $pemesanan);

        try {
            $pembayaran = $this->service->unggahBukti(
                pemesanan: $pemesanan,
                bukti: $request->file('bukti'),
                jumlahBayar: $request->integer('jumlah_bayar'),
                namaPengirim: $request->input('nama_pengirim'),
                tanggalTransfer: $request->filled('tanggal_transfer')
                    ? Carbon::parse($request->date('tanggal_transfer'))
                    : null,
            );
        } catch (PemesananException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => 'Bukti pembayaran terkirim. Menunggu verifikasi pemilik kos.',
            'data' => new PembayaranResource($pembayaran),
        ], 201);
    }

    /**
     * Menyajikan berkas bukti transfer.
     *
     * Hanya penyewa pemilik pesanan, pemilik kos yang bersangkutan, dan admin
     * yang dapat membukanya. Berkasnya tidak pernah berada di direktori publik.
     */
    public function bukti(Pembayaran $pembayaran): StreamedResponse
    {
        $pembayaran->load('pemesanan.kos');

        $this->authorize('view', $pembayaran);

        $disk = Storage::disk(config('ekost.disk_privat'));

        abort_unless($disk->exists($pembayaran->path_bukti), 404, 'Berkas tidak ditemukan.');

        return $disk->response($pembayaran->path_bukti);
    }

    public function verifikasi(VerifikasiPembayaranRequest $request, Pembayaran $pembayaran): JsonResponse
    {
        $pembayaran->load('pemesanan.kos');

        $this->authorize('verifikasi', $pembayaran);

        try {
            $pembayaran = $request->disetujui()
                ? $this->service->setujui($pembayaran, $request->user(), $request->input('catatan'))
                : $this->service->tolak($pembayaran, $request->user(), $request->string('catatan')->toString());
        } catch (PemesananException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => $request->disetujui()
                ? 'Pembayaran terverifikasi. Pesanan sekarang aktif.'
                : 'Bukti pembayaran ditolak. Penyewa diminta mengunggah ulang.',
            'data' => new PembayaranResource($pembayaran),
        ]);
    }
}
