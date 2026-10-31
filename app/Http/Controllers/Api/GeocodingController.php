<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian alamat untuk menentukan titik kos pada peta.
 *
 * Endpoint ini meneruskan permintaan ke Nominatim dari sisi server, bukan
 * membiarkan peramban memanggilnya langsung. Alasannya ada di
 * GeocodingService: hanya di sisi server User-Agent dapat dipastikan dan
 * jumlah permintaan dapat dikendalikan.
 */
class GeocodingController extends Controller
{
    public function __invoke(Request $request, GeocodingService $geocoding): JsonResponse
    {
        $validasi = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:150'],
        ]);

        return response()->json([
            'data' => $geocoding->cari($validasi['q']),
        ]);
    }
}
