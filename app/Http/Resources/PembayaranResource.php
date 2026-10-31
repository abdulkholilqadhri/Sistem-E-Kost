<?php

namespace App\Http\Resources;

use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * path_bukti tidak pernah muncul di sini. Berkasnya diambil lewat endpoint
 * tersendiri yang memeriksa PembayaranPolicy.
 *
 * @mixin Pembayaran
 */
class PembayaranResource extends JsonResource
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
            'jumlah_bayar' => $this->jumlah_bayar,
            'nama_pengirim' => $this->nama_pengirim,
            'tanggal_transfer' => $this->tanggal_transfer?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'catatan' => $this->catatan,
            'diunggah_pada' => $this->created_at?->toIso8601String(),
            'diverifikasi_pada' => $this->diverifikasi_pada?->toIso8601String(),
        ];
    }
}
