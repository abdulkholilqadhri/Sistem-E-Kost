<?php

namespace App\Http\Requests\Pembayaran;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifikasiPembayaranRequest extends FormRequest
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
            'keputusan' => ['required', Rule::in(['setujui', 'tolak'])],
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
            'catatan.required' => 'Alasan penolakan bukti pembayaran wajib diisi.',
        ];
    }

    public function disetujui(): bool
    {
        return $this->input('keputusan') === 'setujui';
    }
}
