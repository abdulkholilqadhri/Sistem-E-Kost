<?php

namespace App\Http\Resources;

use App\Models\Ulasan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ulasan dapat dibaca siapa saja, termasuk pengunjung yang belum masuk.
 *
 * Karena itu penulisnya TIDAK disajikan lewat UserResource, yang memuat email
 * dan nomor HP. Yang keluar hanya namanya. Memakai UserResource di sini akan
 * membocorkan alamat email setiap penyewa yang pernah menulis ulasan kepada
 * siapa pun yang membuka halaman detail kos.
 *
 * @mixin Ulasan
 */
class UlasanResource extends JsonResource
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
            'rating' => $this->rating,
            'komentar' => $this->komentar,
            'ditulis_pada' => $this->created_at?->toIso8601String(),

            'penulis' => $this->whenLoaded('penyewa', fn () => [
                'nama' => $this->penyewa->name,
            ]),

            'kos' => $this->whenLoaded('kos', fn () => [
                'id' => $this->kos->id,
                'nama_kos' => $this->kos->nama_kos,
            ]),
        ];
    }
}
