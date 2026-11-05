<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Identitas pemilik kos untuk konsumsi publik.
 *
 * MENGAPA BUKAN UserResource
 *
 * GET /api/kos/{kos} dapat dibuka siapa saja tanpa login, dan ia memuat relasi
 * pemilik. Bila relasi itu diserialisasi dengan UserResource, alamat surel
 * setiap pemilik kos — beserta status akun dan alasan penonaktifannya —
 * terbaca oleh siapa pun yang membuka halaman detail, termasuk pengumpul
 * alamat surel otomatis.
 *
 * Yang dibutuhkan halaman detail hanyalah nama. Nomor telepon diberikan
 * terpisah lewat PemesananResource, hanya kepada penyewa yang memang sudah
 * punya pesanan di kos itu dan perlu menghubungi pemiliknya.
 *
 * @mixin User
 */
class PemilikPublikResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->name,
        ];
    }
}
