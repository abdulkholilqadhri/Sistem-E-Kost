<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kos\UnggahFotoKosRequest;
use App\Http\Resources\FotoKosResource;
use App\Models\FotoKos;
use App\Models\Kos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Foto kos disimpan pada tabel tersendiri dengan relasi 1:N, bukan sebagai
 * satu kolom pada tabel kos seperti pada ERD di SDD. Mockup detail kos
 * menampilkan sampai 12 foto, dan satu kolom VARCHAR tidak dapat menampungnya.
 */
class FotoKosController extends Controller
{
    private const FOLDER = 'foto-kos';

    public function store(UnggahFotoKosRequest $request, Kos $kos): JsonResponse
    {
        $this->authorize('kelolaFoto', $kos);

        $maksimum = (int) config('ekost.maksimum_foto_per_kos');
        $terpakai = $kos->foto()->count();
        $diunggah = count($request->file('foto'));

        if ($terpakai + $diunggah > $maksimum) {
            return response()->json([
                'pesan' => "Kos ini sudah memiliki {$terpakai} foto. Batas maksimum {$maksimum} foto.",
            ], 422);
        }

        $disk = config('ekost.disk_publik');
        $urutanBerikutnya = (int) $kos->foto()->max('urutan') + 1;
        $keterangan = $request->input('keterangan', []);
        $tersimpan = [];

        foreach ($request->file('foto') as $index => $berkas) {
            $path = $berkas->store(self::FOLDER, $disk);

            if ($path === false) {
                continue;
            }

            $foto = new FotoKos([
                'path_foto' => $path,
                'keterangan' => $keterangan[$index] ?? null,
                'urutan' => $urutanBerikutnya++,
            ]);

            $kos->foto()->save($foto);
            $tersimpan[] = $foto;
        }

        return response()->json([
            'pesan' => count($tersimpan).' foto diunggah.',
            'data' => FotoKosResource::collection($tersimpan),
        ], 201);
    }

    public function destroy(Kos $kos, FotoKos $foto): JsonResponse
    {
        $this->authorize('kelolaFoto', $kos);

        // Memastikan foto benar-benar milik kos pada URL. Tanpa pemeriksaan
        // ini, pemilik kos A dapat menghapus foto kos B hanya dengan memasang
        // id kosnya sendiri di bagian depan URL.
        abort_unless($foto->kos_id === $kos->id, 404);

        Storage::disk(config('ekost.disk_publik'))->delete($foto->path_foto);
        $foto->delete();

        return response()->json(['pesan' => 'Foto dihapus.']);
    }
}
