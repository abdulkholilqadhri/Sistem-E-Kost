<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Http\Requests\PengajuanPemilik\VerifikasiPengajuanRequest;
use App\Http\Resources\PengajuanPemilikResource;
use App\Models\PengajuanPemilik;
use App\Services\PengajuanPemilikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sisi admin untuk FR-15: memeriksa berkas dan memutuskan pengajuan.
 */
class VerifikasiPengajuanController extends Controller
{
    public function __construct(private readonly PengajuanPemilikService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PengajuanPemilik::class);

        $pengajuan = PengajuanPemilik::query()
            ->with(['user', 'verifikator'])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString()),
                fn ($query) => $query->where('status', StatusPengajuan::Menunggu),
            )
            ->latest()
            ->paginate(20);

        return PengajuanPemilikResource::collection($pengajuan);
    }

    public function show(PengajuanPemilik $pengajuan): PengajuanPemilikResource
    {
        $this->authorize('view', $pengajuan);

        return new PengajuanPemilikResource($pengajuan->load(['user', 'verifikator']));
    }

    /**
     * Menyajikan berkas KTP atau dokumen kepemilikan.
     *
     * Berkas tidak pernah berada di direktori publik dan path-nya tidak pernah
     * dikirim ke klien. Satu-satunya cara membukanya adalah lewat endpoint ini,
     * yang memeriksa Policy lebih dulu.
     */
    public function dokumen(PengajuanPemilik $pengajuan, string $jenis): StreamedResponse
    {
        $this->authorize('lihatDokumen', $pengajuan);

        $path = match ($jenis) {
            'ktp' => $pengajuan->path_ktp,
            'kepemilikan' => $pengajuan->path_dokumen_kepemilikan,
            default => null,
        };

        abort_if($path === null, 404, 'Dokumen tidak ditemukan.');

        $disk = Storage::disk(config('ekost.disk_privat'));

        abort_unless($disk->exists($path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        return $disk->response($path);
    }

    public function verifikasi(VerifikasiPengajuanRequest $request, PengajuanPemilik $pengajuan): JsonResponse
    {
        $this->authorize('verifikasi', $pengajuan);

        $pengajuan = $request->keputusan() === StatusPengajuan::Disetujui
            ? $this->service->setujui($pengajuan, $request->user(), $request->input('catatan_admin'))
            : $this->service->tolak($pengajuan, $request->user(), $request->string('catatan_admin')->toString());

        return response()->json([
            'pesan' => $pengajuan->status === StatusPengajuan::Disetujui
                ? 'Pengajuan disetujui. Peran pengguna telah diubah menjadi Pemilik Kos.'
                : 'Pengajuan ditolak.',
            'data' => new PengajuanPemilikResource($pengajuan->load(['user', 'verifikator'])),
        ]);
    }
}
