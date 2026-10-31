<?php

namespace App\Http\Requests\PengajuanPemilik;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi berkas unggahan pengajuan pemilik kos (FR-14).
 *
 * Aturan mimes dan mimetypes dipakai bersama-sama dengan sengaja.
 * Memeriksa ekstensi saja tidak cukup: berkas PHP yang dinamai foto.jpg akan
 * lolos pemeriksaan ekstensi. mimetypes memeriksa tipe berkas yang sebenarnya
 * berdasarkan isinya, bukan namanya.
 */
class AjukanPemilikRequest extends FormRequest
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
        $maksimum = config('ekost.ukuran_maksimum_kb.dokumen');

        return [
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'ktp' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                "max:{$maksimum}",
            ],
            'dokumen_kepemilikan' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                "max:{$maksimum}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ktp.required' => 'Berkas KTP wajib diunggah.',
            'ktp.mimes' => 'KTP harus berupa berkas JPG, PNG, atau PDF.',
            'ktp.mimetypes' => 'Isi berkas tidak cocok dengan jenis yang diizinkan.',
            'dokumen_kepemilikan.mimetypes' => 'Isi berkas tidak cocok dengan jenis yang diizinkan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ktp' => 'KTP',
            'dokumen_kepemilikan' => 'dokumen kepemilikan',
        ];
    }
}
