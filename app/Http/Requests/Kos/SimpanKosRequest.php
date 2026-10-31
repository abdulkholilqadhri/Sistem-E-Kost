<?php

namespace App\Http\Requests\Kos;

use App\Enums\StatusKos;
use App\Enums\TipeKos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data kos saat dibuat maupun disunting.
 *
 * Catatan tentang sisa_kamar: saat kos dibuat, kolom ini tidak diterima dari
 * permintaan melainkan disamakan dengan jumlah_kamar. Saat disunting, pemilik
 * boleh menyetelnya — misalnya karena ada penyewa yang masuk di luar sistem —
 * tetapi angka itu akan ditimpa oleh alur pemesanan setiap kali ada pesanan
 * diterima, dibatalkan, atau selesai.
 */
class SimpanKosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi ditangani Policy di controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $aturan = [
            'nama_kos' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'alamat' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'tipe_kos' => ['required', Rule::enum(TipeKos::class)],
            'harga_per_bulan' => ['required', 'integer', 'min:0', 'max:100000000'],
            'jumlah_kamar' => ['required', 'integer', 'min:1', 'max:1000'],
            'fasilitas' => ['nullable', 'array', 'max:20'],
            'fasilitas.*' => ['string', 'max:50'],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $aturan['sisa_kamar'] = ['required', 'integer', 'min:0', 'lte:jumlah_kamar'];
            $aturan['status'] = ['required', Rule::enum(StatusKos::class)];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Titik lokasi kos pada peta wajib ditentukan.',
            'longitude.required' => 'Titik lokasi kos pada peta wajib ditentukan.',
            'sisa_kamar.lte' => 'Sisa kamar tidak boleh melebihi jumlah kamar.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama_kos' => 'nama kos',
            'tipe_kos' => 'tipe kos',
            'harga_per_bulan' => 'harga per bulan',
            'jumlah_kamar' => 'jumlah kamar',
            'sisa_kamar' => 'sisa kamar',
        ];
    }
}
