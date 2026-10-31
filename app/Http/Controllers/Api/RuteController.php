<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kos\CariKosRequest;
use App\Models\Kos;
use App\Services\PencarianKosService;
use App\Services\RuteService;
use Illuminate\Http\JsonResponse;

/**
 * FR-06 — Rute dan jarak tempuh dari titik acuan ke sebuah kos.
 */
class RuteController extends Controller
{
    public function __construct(
        private readonly RuteService $rute,
        private readonly PencarianKosService $pencarian,
    ) {}

    /**
     * CariKosRequest dipakai ulang di sini karena aturan titik acuannya sama
     * persis: boleh dari parameter lat/lng, boleh dari koordinat kampus, dan
     * ditolak dengan jelas bila keduanya tidak tersedia.
     */
    public function __invoke(CariKosRequest $request, Kos $kos): JsonResponse
    {
        $this->authorize('view', $kos);

        $dariLat = $request->lintang();
        $dariLng = $request->bujur();

        $jarakLurus = (int) round($this->pencarian->jarakHaversine(
            $dariLat,
            $dariLng,
            (float) $kos->latitude,
            (float) $kos->longitude,
        ));

        $hasil = $this->rute->rute($dariLat, $dariLng, (float) $kos->latitude, (float) $kos->longitude);

        // Layanan rute tidak tersedia. Permintaan tetap dijawab 200 dengan
        // jarak garis lurus, bukan dijawab dengan kesalahan: peta dan detail
        // kos harus tetap berguna meskipun OSRM sedang mati (NFR-04.2).
        if ($hasil === null) {
            return response()->json([
                'tersedia' => false,
                'pesan' => 'Layanan rute sedang tidak tersedia. Yang ditampilkan jarak garis lurus.',
                'jarak_lurus_meter' => $jarakLurus,
                'rute' => null,
            ]);
        }

        return response()->json([
            'tersedia' => true,
            'jarak_lurus_meter' => $jarakLurus,
            'rute' => [
                'jarak_tempuh_meter' => $hasil['jarak_meter'],
                'durasi_detik' => $hasil['durasi_detik'],
                'geometri' => $hasil['geometri'],
            ],
        ]);
    }
}
