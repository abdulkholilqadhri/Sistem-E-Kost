<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PengajuanPemilik\AjukanPemilikRequest;
use App\Http\Resources\PengajuanPemilikResource;
use App\Models\PengajuanPemilik;
use App\Services\PengajuanPemilikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * Sisi pemohon untuk FR-14. Penyewa mengajukan diri menjadi pemilik kos dan
 * memantau status pengajuannya sendiri.
 */
class PengajuanPemilikController extends Controller
{
    public function __construct(private readonly PengajuanPemilikService $service) {}

    /**
     * Riwayat pengajuan milik pengguna yang sedang masuk.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $pengajuan = PengajuanPemilik::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return PengajuanPemilikResource::collection($pengajuan);
    }

    public function store(AjukanPemilikRequest $request): JsonResponse
    {
        $this->authorize('create', PengajuanPemilik::class);

        try {
            $pengajuan = $this->service->ajukan(
                pemohon: $request->user(),
                namaLengkap: $request->string('nama_lengkap')->toString(),
                alamat: $request->input('alamat'),
                ktp: $request->file('ktp'),
                dokumenKepemilikan: $request->file('dokumen_kepemilikan'),
            );
        } catch (RuntimeException $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        }

        return response()->json([
            'pesan' => 'Pengajuan terkirim. Admin akan memverifikasi berkas Anda.',
            'data' => new PengajuanPemilikResource($pengajuan),
        ], 201);
    }

    public function show(PengajuanPemilik $pengajuan): PengajuanPemilikResource
    {
        $this->authorize('view', $pengajuan);

        return new PengajuanPemilikResource($pengajuan->load('verifikator'));
    }
}
