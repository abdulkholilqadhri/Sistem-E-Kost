<?php

namespace App\Http\Resources;

use App\Models\FotoKos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin FotoKos
 */
class FotoKosResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->alamatFoto(),
            'keterangan' => $this->keterangan,
            'urutan' => $this->urutan,
        ];
    }

    /**
     * Alamat yang dipakai peramban untuk memuat foto.
     *
     * Nilai yang sudah berupa alamat jadi — diawali skema http, atau garis
     * miring yang menandakan berkas di direktori public — diteruskan apa
     * adanya. Itu hanya terjadi pada data simulasi dari seeder.
     *
     * Foto yang benar-benar diunggah pemilik selalu tersimpan di disk publik
     * dengan path relatif tanpa garis miring di depan, sehingga tetap
     * melewati Storage::url seperti biasa.
     */
    private function alamatFoto(): string
    {
        $path = (string) $this->path_foto;

        $sudahJadi = str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/');

        return $sudahJadi
            ? $path
            : Storage::disk(config('ekost.disk_publik'))->url($path);
    }
}
