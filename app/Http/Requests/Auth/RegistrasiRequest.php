<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Registrasi hanya membuat akun dengan peran Penyewa. Peran tidak diterima
 * dari permintaan, sehingga tidak ada jalan bagi pendaftar untuk memilih
 * menjadi Pemilik atau Admin. Lihat UC-01 pada SRS dan FR-14/FR-15: jalan
 * menjadi pemilik kos adalah pengajuan yang diverifikasi admin.
 */
class RegistrasiRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'no_hp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda + dan -.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'no_hp' => 'nomor HP',
            'password' => 'kata sandi',
        ];
    }
}
