<?php

namespace App\Http\Requests\Pembayaran;

use Illuminate\Foundation\Http\FormRequest;

class UnggahBuktiRequest extends FormRequest
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
        $maksimum = config('ekost.ukuran_maksimum_kb.bukti_bayar');

        return [
            'bukti' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                "max:{$maksimum}",
            ],
            'jumlah_bayar' => ['required', 'integer', 'min:1'],
            'nama_pengirim' => ['nullable', 'string', 'max:255'],
            'tanggal_transfer' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bukti.required' => 'Unggah bukti transfer terlebih dahulu.',
            'bukti.mimetypes' => 'Isi berkas tidak cocok dengan jenis yang diizinkan.',
            'tanggal_transfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bukti' => 'bukti transfer',
            'jumlah_bayar' => 'jumlah transfer',
            'nama_pengirim' => 'nama pengirim',
            'tanggal_transfer' => 'tanggal transfer',
        ];
    }
}
