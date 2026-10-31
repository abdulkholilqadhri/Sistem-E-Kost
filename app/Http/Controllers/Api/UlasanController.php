<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ulasan\SimpanUlasanRequest;
use App\Http\Resources\UlasanResource;
use App\Models\Kos;
use App\Models\Pemesanan;
use App\Models\Ulasan;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * FR-09 — Ulasan dan rating kos.
 *
 * Ulasan hanya dapat ditulis oleh penyewa yang pemesanannya sudah berstatus
 * selesai, dan hanya sekali per pemesanan. Dua pembatasan itu yang membuat
 * rating di sistem ini berarti: tanpa keduanya, siapa pun dapat menaikkan
 * atau menjatuhkan nilai sebuah kos tanpa pernah menginap di sana.
 */
class UlasanController extends Controller
{
    /**
     * Ulasan sebuah kos. Terbuka untuk umum.
     */
    public function index(Kos $kos): AnonymousResourceCollection
    {
        $this->authorize('view', $kos);

        $ulasan = $kos->ulasan()
            ->with('penyewa')
            ->latest()
            ->paginate(10);

        return UlasanResource::collection($ulasan);
    }

    /**
     * Ulasan terbaru lintas kos, untuk halaman depan.
     *
     * Tidak disaring menurut nilai bintangnya. Menampilkan hanya ulasan
     * berbintang tinggi akan membuat halaman depan memberi gambaran yang
     * tidak jujur tentang isi sistem — dan rating yang tampak selalu bagus
     * justru berhenti dipercaya pembacanya.
     */
    public function terbaru(): AnonymousResourceCollection
    {
        $ulasan = Ulasan::query()
            ->whereNotNull('komentar')
            ->whereHas('kos', fn ($query) => $query->where('status', StatusKos::Aktif))
            ->with(['penyewa', 'kos'])
            ->latest()
            ->limit(6)
            ->get();

        return UlasanResource::collection($ulasan);
    }

    public function store(SimpanUlasanRequest $request, Pemesanan $pemesanan): JsonResponse
    {
        // Relasi kos dimuat lebih dulu karena Policy memeriksanya.
        $pemesanan->load(['kos', 'ulasan']);

        $this->authorize('ulas', $pemesanan);

        $ulasan = new Ulasan($request->validated());
        $ulasan->pemesanan_id = $pemesanan->id;
        $ulasan->penyewa_id = $pemesanan->penyewa_id;

        // kos_id disalin dari pemesanan, bukan diterima dari permintaan.
        // Bila diterima dari permintaan, penyewa dapat menitipkan ulasannya
        // pada kos milik orang lain yang tidak pernah ia tempati.
        $ulasan->kos_id = $pemesanan->kos_id;

        try {
            $ulasan->save();
        } catch (QueryException $e) {
            // Kolom pemesanan_id bersifat unique. Dua permintaan yang tiba
            // bersamaan dapat sama-sama lolos pemeriksaan Policy, dan yang
            // kedua ditolak di tingkat basis data. Inilah penjaga sebenarnya
            // terhadap ulasan ganda.
            if ($this->pelanggaranUnik($e)) {
                return response()->json([
                    'pesan' => 'Pesanan ini sudah pernah Anda ulas.',
                ], 422);
            }

            throw $e;
        }

        return response()->json([
            'pesan' => 'Terima kasih, ulasan Anda tersimpan.',
            'data' => new UlasanResource($ulasan->load('penyewa')),
        ], 201);
    }

    public function destroy(Ulasan $ulasan): JsonResponse
    {
        $this->authorize('delete', $ulasan);

        $ulasan->delete();

        return response()->json(['pesan' => 'Ulasan dihapus.']);
    }

    private function pelanggaranUnik(QueryException $e): bool
    {
        // 23000 adalah kelas SQLSTATE untuk pelanggaran batasan integritas,
        // dipakai MySQL maupun SQLite.
        return $e->getCode() === '23000';
    }
}
