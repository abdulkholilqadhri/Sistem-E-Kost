<?php

namespace App\Http\Requests\Ulasan;

use Illuminate\Foundation\Http\FormRequest;

class SimpanUlasanRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'komentar' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Beri penilaian bintang terlebih dahulu.',
            'rating.between' => 'Penilaian hanya dari 1 sampai 5 bintang.',
        ];
    }
}
