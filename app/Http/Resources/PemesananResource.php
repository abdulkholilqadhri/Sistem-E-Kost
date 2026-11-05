<?php

namespace App\Http\Resources;

use App\Models\Pemesanan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pemesanan
 */
class PemesananResource extends JsonResource
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
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
            'durasi_bulan' => $this->durasi_bulan,
            'harga_per_bulan' => $this->harga_per_bulan,
            'total_harga' => $this->total_harga,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'catatan_pemilik' => $this->catatan_pemilik,
            'dikonfirmasi_pada' => $this->dikonfirmasi_pada?->toIso8601String(),

            // Dipakai antarmuka untuk hitung mundur tenggat pembayaran.
            // Pembatalan otomatis saat tenggat lewat dikerjakan perintah
            // terjadwal pemesanan:kedaluwarsa, bukan oleh hitung mundur di
            // sisi klien.
            'batas_pembayaran' => $this->batas_pembayaran?->toIso8601String(),

            'diajukan_pada' => $this->created_at?->toIso8601String(),
            'boleh_diulas' => $this->bolehDiulas(),

            'kos' => new KosResource($this->whenLoaded('kos')),
            'penyewa' => new UserResource($this->whenLoaded('penyewa')),
            'pembayaran' => new PembayaranResource($this->whenLoaded('pembayaran')),

            /*
             * Kontak pemilik untuk halaman pembayaran.
             *
             * Sistem ini tidak memegang uang: transfer dilakukan langsung ke
             * pemilik kos dan sistem hanya mencatat buktinya. Penyewa karena
             * itu perlu cara menghubungi pemiliknya untuk menanyakan nomor
             * rekening tujuan.
             *
             * Nomor telepon hanya muncul di sini, tidak di detail kos yang
             * terbuka untuk umum: yang membaca ini sudah punya pesanan di kos
             * tersebut, dan PemesananPolicy sudah memastikan ia pihak yang
             * berhak atas pesanan itu.
             */
            'kontak_pemilik' => $this->when(
                $this->relationLoaded('kos') && $this->kos?->relationLoaded('pemilik'),
                fn () => [
                    'nama' => $this->kos->pemilik?->name,
                    'no_hp' => $this->kos->pemilik?->no_hp,
                ],
            ),
        ];
    }
}
