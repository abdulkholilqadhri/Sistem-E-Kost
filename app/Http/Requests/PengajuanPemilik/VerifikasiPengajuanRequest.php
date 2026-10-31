<?php

namespace App\Http\Requests\PengajuanPemilik;

use App\Enums\StatusPengajuan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Keputusan admin atas sebuah pengajuan (FR-15).
 *
 * Penolakan wajib disertai alasan. Pemohon yang ditolak tanpa penjelasan tidak
 * tahu apa yang harus diperbaiki pada pengajuan berikutnya.
 */
class VerifikasiPengajuanRequest extends FormRequest
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
            'keputusan' => [
                'required',
                Rule::in([StatusPengajuan::Disetujui->value, StatusPengajuan::Ditolak->value]),
            ],
            'catatan_admin' => [
                Rule::requiredIf($this->input('keputusan') === StatusPengajuan::Ditolak->value),
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
            'keputusan.in' => 'Keputusan hanya boleh disetujui atau ditolak.',
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
        ];
    }

    public function keputusan(): StatusPengajuan
    {
        return StatusPengajuan::from($this->string('keputusan')->toString());
    }
}
