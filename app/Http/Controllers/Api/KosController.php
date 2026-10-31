<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kos\CariKosRequest;
use App\Http\Requests\Kos\SimpanKosRequest;
use App\Http\Resources\KosResource;
use App\Models\Kos;
use App\Services\PencarianKosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * FR-04 (sebaran kos di peta), FR-05 (pencarian berbasis lokasi), FR-07
 * (detail kos), dan pengelolaan kos oleh pemilik.
 */
class KosController extends Controller
{
    public function __construct(private readonly PencarianKosService $pencarian) {}

    /**
     * Daftar kos tayang tanpa perhitungan jarak. Dipakai untuk menaruh penanda
     * di peta saat pengguna belum menentukan titik acuan.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $kos = Kos::query()
            ->tayang()
            ->with('foto')
            ->withAvg('ulasan', 'rating')
            ->withCount('ulasan')
            ->latest()
            ->paginate((int) $request->input('per_halaman', 15));

        return KosResource::collection($kos);
    }

    /**
     * Pencarian berbasis jarak dari titik acuan.
     */
    public function cari(CariKosRequest $request): AnonymousResourceCollection
    {
        $hasil = $this->pencarian->cari(
            lat: $request->lintang(),
            lng: $request->bujur(),
            radiusMeter: $request->radiusMeter(),
            filter: $request->filter(),
            perHalaman: $request->perHalaman(),
            halaman: (int) $request->input('page', 1),
        );

        return KosResource::collection($hasil)->additional([
            'acuan' => [
                'lat' => $request->lintang(),
                'lng' => $request->bujur(),
                'radius_meter' => $request->radiusMeter(),
                'jenis_jarak' => 'garis_lurus',
            ],
        ]);
    }

    public function show(Kos $kos): KosResource
    {
        $this->authorize('view', $kos);

        $kos->load(['foto', 'pemilik'])
            ->loadAvg('ulasan', 'rating')
            ->loadCount('ulasan');

        return new KosResource($kos);
    }

    /**
     * Daftar kos milik pemilik yang sedang masuk, termasuk yang tidak tayang.
     */
    public function milikSaya(Request $request): AnonymousResourceCollection
    {
        $kos = Kos::query()
            ->where('pemilik_id', $request->user()->id)
            ->with('foto')
            ->withCount('ulasan')
            ->latest()
            ->paginate(15);

        return KosResource::collection($kos);
    }

    public function store(SimpanKosRequest $request): JsonResponse
    {
        $this->authorize('create', Kos::class);

        $kos = new Kos($request->safe()->except(['sisa_kamar', 'status']));
        $kos->pemilik_id = $request->user()->id;

        // Kos baru dianggap kosong seluruhnya. Pengurangan kamar sepenuhnya
        // dikendalikan alur pemesanan, bukan angka yang diketik pemilik.
        $kos->sisa_kamar = $request->integer('jumlah_kamar');
        $kos->status = StatusKos::Aktif;
        $kos->save();

        return response()->json([
            'pesan' => 'Kos berhasil ditambahkan.',
            'data' => new KosResource($kos->load('foto')),
        ], 201);
    }

    public function update(SimpanKosRequest $request, Kos $kos): JsonResponse
    {
        $this->authorize('update', $kos);

        $kos->fill($request->safe()->all());
        $kos->save();

        return response()->json([
            'pesan' => 'Data kos diperbarui.',
            'data' => new KosResource($kos->load('foto')),
        ]);
    }

    public function destroy(Kos $kos): JsonResponse
    {
        $this->authorize('delete', $kos);

        // Relasi pemesanan memakai restrictOnDelete, sehingga kos yang pernah
        // dipesan tidak dapat dihapus. Riwayat pemesanan tidak boleh hilang
        // hanya karena pemilik membersihkan daftar kosnya; yang tepat untuk
        // itu adalah menonaktifkan kos, bukan menghapusnya.
        if ($kos->pemesanan()->exists()) {
            return response()->json([
                'pesan' => 'Kos ini memiliki riwayat pemesanan dan tidak dapat dihapus. '
                    .'Ubah statusnya menjadi tidak tayang bila ingin menyembunyikannya.',
            ], 422);
        }

        $kos->delete();

        return response()->json(['pesan' => 'Kos dihapus.']);
    }
}
