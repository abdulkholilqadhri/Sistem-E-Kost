<?php

namespace App\Http\Resources;

use App\Models\PengajuanPemilik;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Path berkas tidak pernah muncul di sini. Yang disampaikan hanya keterangan
 * bahwa berkasnya ada; pengambilannya lewat endpoint tersendiri yang memeriksa
 * izin admin.
 *
 * @mixin PengajuanPemilik
 */
class PengajuanPemilikResource extends JsonResource
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
            'nama_lengkap' => $this->nama_lengkap,
            'alamat' => $this->alamat,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'catatan_admin' => $this->catatan_admin,
            'ada_dokumen_kepemilikan' => $this->path_dokumen_kepemilikan !== null,
            'diajukan_pada' => $this->created_at?->toIso8601String(),
            'diverifikasi_pada' => $this->diverifikasi_pada?->toIso8601String(),
            'pemohon' => new UserResource($this->whenLoaded('user')),
            'verifikator' => new UserResource($this->whenLoaded('verifikator')),
        ];
    }
}
