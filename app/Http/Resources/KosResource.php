<?php

namespace App\Http\Resources;

use App\Models\Kos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kos
 */
class KosResource extends JsonResource
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
            'nama_kos' => $this->nama_kos,
            'deskripsi' => $this->deskripsi,
            'alamat' => $this->alamat,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'tipe_kos' => $this->tipe_kos->value,
            'tipe_kos_label' => $this->tipe_kos->label(),
            'harga_per_bulan' => $this->harga_per_bulan,
            'jumlah_kamar' => $this->jumlah_kamar,
            'sisa_kamar' => $this->sisa_kamar,
            'tersedia' => $this->tersedia(),
            'fasilitas' => $this->fasilitas ?? [],
            'status' => $this->status->value,

            // Hanya terisi pada hasil pencarian berbasis jarak. Angkanya jarak
            // garis lurus (Haversine), bukan jarak tempuh lewat jalan — rute
            // dari OSRM akan memberi angka yang lebih besar. Keduanya harus
            // diberi label berbeda di antarmuka agar pengguna tidak bingung.
            'jarak_meter' => $this->whenNotNull($this->getAttribute('jarak_meter')),

            'rating_rata' => $this->whenNotNull(
                $this->ulasan_avg_rating !== null ? round((float) $this->ulasan_avg_rating, 1) : null,
            ),
            'jumlah_ulasan' => $this->whenNotNull($this->ulasan_count),

            'foto' => FotoKosResource::collection($this->whenLoaded('foto')),

            // Sengaja bukan UserResource: endpoint detail kos terbuka untuk
            // umum, sedangkan UserResource memuat surel dan status akun.
            // Penjelasan lengkapnya ada di PemilikPublikResource.
            'pemilik' => new PemilikPublikResource($this->whenLoaded('pemilik')),
        ];
    }
}
