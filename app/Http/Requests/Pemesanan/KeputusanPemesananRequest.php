<?php

namespace App\Http\Requests\Pemesanan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Keputusan pemilik atas pesanan masuk. Penolakan wajib beralasan agar
 * penyewa tahu apakah perlu mencoba lagi atau mencari kos lain.
 */
class KeputusanPemesananRequest extends FormRequest
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
        return [
            'keputusan' => ['required', Rule::in(['terima', 'tolak'])],
            'catatan' => [
                Rule::requiredIf($this->input('keputusan') === 'tolak'),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keputusan.in' => 'Keputusan hanya boleh terima atau tolak.',
            'catatan.required' => 'Alasan penolakan wajib diisi.',
        ];
    }

    public function diterima(): bool
    {
        return $this->input('keputusan') === 'terima';
    }
}
