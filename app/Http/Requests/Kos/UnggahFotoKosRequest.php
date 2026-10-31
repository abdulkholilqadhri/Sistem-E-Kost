<?php

namespace App\Http\Requests\Kos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggahan foto kos.
 *
 * Sama seperti berkas KTP, mimes dan mimetypes dipakai bersama-sama: ekstensi
 * saja tidak membuktikan isi berkas. Bedanya, foto kos memang ditujukan untuk
 * umum, sehingga disimpan pada disk publik — bukan karena pemeriksaannya lebih
 * longgar, melainkan karena isinya memang untuk dilihat siapa saja.
 */
class UnggahFotoKosRequest extends FormRequest
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
        $maksimum = config('ekost.ukuran_maksimum_kb.foto_kos');

        return [
            'foto' => ['required', 'array', 'max:'.config('ekost.maksimum_foto_per_kos')],
            'foto.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maksimum}",
            ],
            'keterangan' => ['nullable', 'array'],
            'keterangan.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'foto.required' => 'Pilih sedikitnya satu foto.',
            'foto.*.mimes' => 'Foto harus berupa berkas JPG, PNG, atau WebP.',
            'foto.*.mimetypes' => 'Isi berkas tidak cocok dengan jenis gambar yang diizinkan.',
        ];
    }
}
