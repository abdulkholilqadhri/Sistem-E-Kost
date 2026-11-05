<?php

namespace App\Http\Requests\Admin;

use App\Enums\StatusAkun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Perubahan status akun oleh admin (FR-16).
 *
 * Penonaktifan wajib disertai alasan. Pengguna yang tiba-tiba tidak bisa masuk
 * akan menghubungi admin menanyakan sebabnya, dan admin yang memutuskan bulan
 * lalu tidak akan ingat — alasan itu disimpan untuk menjawabnya.
 */
class UbahStatusAkunRequest extends FormRequest
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
            'status_akun' => [
                'required',
                Rule::in([StatusAkun::Aktif->value, StatusAkun::Nonaktif->value]),
            ],
            'alasan' => [
                Rule::requiredIf($this->input('status_akun') === StatusAkun::Nonaktif->value),
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
            'status_akun.in' => 'Status akun hanya boleh aktif atau nonaktif.',
            'alasan.required' => 'Alasan penonaktifan wajib diisi.',
        ];
    }

    public function statusAkun(): StatusAkun
    {
        return StatusAkun::from($this->string('status_akun')->toString());
    }
}
