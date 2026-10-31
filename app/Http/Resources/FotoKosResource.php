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
            'url' => Storage::disk(config('ekost.disk_publik'))->url($this->path_foto),
            'keterangan' => $this->keterangan,
            'urutan' => $this->urutan,
        ];
    }
}
