<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PemesananException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pemesanan\AjukanPemesananRequest;
use App\Http\Requests\Pemesanan\KeputusanPemesananRequest;
use App\Http\Resources\PemesananResource;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Services\PemesananService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * FR-08 (pemesanan kos) dan FR-11 (konfirmasi pesanan).
 */
class PemesananController extends Controller
{
    public function __construct(private readonly PemesananService $service) {}

    /**
     * Pesanan milik penyewa yang sedang masuk.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $pemesanan = Pemesanan::query()
            ->where('penyewa_id', $request->user()->id)
            ->with(['kos.foto', 'pembayaran'])
            ->latest()
            ->paginate(15);

        return PemesananResource::collection($pemesanan);
    }

    /**
     * Pesanan yang masuk ke kos milik pemilik yang sedang masuk.
     */
    public function masuk(Request $request): AnonymousResourceCollection
    {
        $pemesanan = Pemesanan::query()
            ->whereHas('kos', fn ($query) => $query->where('pemilik_id', $request->user()->id))
            ->with(['kos', 'penyewa', 'pembayaran'])
            ->latest()
            ->paginate(15);

        return PemesananResource::collection($pemesanan);
    }

    public function show(Pemesanan $pemesanan): PemesananResource
    {
        $pemesanan->load('kos');

        $this->authorize('view', $pemesanan);

        // kos.pemilik dimuat agar halaman pembayaran dapat menampilkan kontak
        // pemiliknya. Yang diserialisasi hanya nama dan nomor telepon; lihat
        // kontak_pemilik di PemesananResource.
        return new PemesananResource($pemesanan->load(['kos.foto', 'kos.pemilik', 'penyewa', 'pembayaran']));
    }

    public function store(AjukanPemesananRequest $request): JsonResponse
    {
        $this->authorize('create', Pemesanan::class);

        $kos = Kos::findOrFail($request->integer('kos_id'));

        try {
            $pemesanan = $this->service->ajukan(
                penyewa: $request->user(),
                kos: $kos,
                tanggalMulai: Carbon::parse($request->date('tanggal_mulai')),
                durasiBulan: $request->integer('durasi_bulan'),
            );
        } catch (PemesananException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => 'Pesanan terkirim. Menunggu konfirmasi pemilik kos.',
            'data' => new PemesananResource($pemesanan->load('kos')),
        ], 201);
    }

    /**
     * Pemilik menerima atau menolak pesanan masuk.
     */
    public function keputusan(KeputusanPemesananRequest $request, Pemesanan $pemesanan): JsonResponse
    {
        // Relasi kos dimuat lebih dulu karena Policy memeriksa pemilik kos.
        $pemesanan->load('kos');

        $this->authorize('konfirmasi', $pemesanan);

        try {
            $pemesanan = $request->diterima()
                ? $this->service->terima($pemesanan)
                : $this->service->tolak($pemesanan, $request->string('catatan')->toString());
        } catch (PemesananException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => $request->diterima()
                ? 'Pesanan diterima. Penyewa diminta mengunggah bukti pembayaran.'
                : 'Pesanan ditolak.',
            'data' => new PemesananResource($pemesanan->load(['kos', 'penyewa'])),
        ]);
    }

    /**
     * Pembatalan oleh penyewa.
     */
    public function batal(Pemesanan $pemesanan): JsonResponse
    {
        $this->authorize('batalkan', $pemesanan);

        try {
            $pemesanan = $this->service->batalkan($pemesanan);
        } catch (PemesananException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => 'Pesanan dibatalkan.',
            'data' => new PemesananResource($pemesanan->load('kos')),
        ]);
    }
}
