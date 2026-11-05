<?php

namespace App\Http\Requests\Kos;

use App\Enums\TipeKos;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Parameter pencarian kos berdasarkan jarak.
 *
 * Bila klien tidak mengirim titik acuan, dipakai koordinat kampus dari
 * konfigurasi. Bila koordinat itu pun belum diisi di .env, permintaan ditolak
 * dengan pesan yang jelas — bukan dibiarkan jatuh ke koordinat 0,0 yang akan
 * memberi hasil kosong tanpa petunjuk apa pun tentang penyebabnya.
 */
class CariKosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'between:100,'.config('ekost.radius_maksimum')],
            'tipe_kos' => ['nullable', Rule::enum(TipeKos::class)],
            'harga_min' => ['nullable', 'integer', 'min:0'],
            'harga_maks' => ['nullable', 'integer', 'min:0', 'gte:harga_min'],

            /*
             * Fasilitas dibatasi pada daftar baku di config/ekost.php.
             *
             * Tanpa Rule::in, klien dapat mengirim nilai apa pun dan
             * penyaringnya akan mengembalikan hasil kosong tanpa penjelasan.
             * Dengan Rule::in, kesalahan ejaan dijawab sebagai galat validasi
             * yang menyebutkan masalahnya.
             */
            'fasilitas' => ['nullable', 'array', 'max:10'],
            'fasilitas.*' => [Rule::in(config('ekost.fasilitas'))],

            'q' => ['nullable', 'string', 'max:100'],
            'per_halaman' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('lat') && $this->filled('lng')) {
                return;
            }

            if (config('ekost.kampus.lat') === null || config('ekost.kampus.lng') === null) {
                $validator->errors()->add(
                    'lat',
                    'Titik acuan pencarian belum tersedia. Kirim parameter lat dan lng, '
                    .'atau isi EKOST_KAMPUS_LAT dan EKOST_KAMPUS_LNG pada berkas .env.',
                );
            }
        });
    }

    public function lintang(): float
    {
        return (float) ($this->input('lat') ?? config('ekost.kampus.lat'));
    }

    public function bujur(): float
    {
        return (float) ($this->input('lng') ?? config('ekost.kampus.lng'));
    }

    public function radiusMeter(): int
    {
        return (int) ($this->input('radius') ?? config('ekost.radius_default'));
    }

    /**
     * @return array{tipe_kos: ?string, harga_min: ?int, harga_maks: ?int, fasilitas: array<int, string>, q: ?string}
     */
    public function filter(): array
    {
        return [
            'tipe_kos' => $this->input('tipe_kos'),
            'harga_min' => $this->integer('harga_min') ?: null,
            'harga_maks' => $this->integer('harga_maks') ?: null,
            'fasilitas' => array_values(array_filter((array) $this->input('fasilitas', []))),
            'q' => $this->input('q'),
        ];
    }

    public function perHalaman(): int
    {
        return (int) ($this->input('per_halaman') ?? 15);
    }
}
