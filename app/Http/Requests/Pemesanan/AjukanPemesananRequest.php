<?php

namespace App\Http\Requests\Pemesanan;

use Illuminate\Foundation\Http\FormRequest;

class AjukanPemesananRequest extends FormRequest
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
            'kos_id' => ['required', 'integer', 'exists:kos,id'],
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'durasi_bulan' => [
                'required',
                'integer',
                'min:1',
                'max:'.config('ekost.durasi_sewa_maksimum_bulan'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal_mulai.after_or_equal' => 'Tanggal mulai sewa tidak boleh di masa lalu.',
            'durasi_bulan.max' => 'Durasi sewa maksimal '.config('ekost.durasi_sewa_maksimum_bulan').' bulan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kos_id' => 'kos',
            'tanggal_mulai' => 'tanggal mulai',
            'durasi_bulan' => 'durasi sewa',
        ];
    }
}
