<?php

namespace App\Http\Requests\Auth;

use App\Enums\StatusAkun;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login dengan pembatasan percobaan (NFR keamanan yang belum tercantum di SRS).
 *
 * Tanpa pembatasan ini, satu skrip dapat mencoba ribuan kata sandi per menit
 * terhadap satu alamat email. Lima percobaan gagal mengunci kombinasi
 * email + alamat IP selama satu menit.
 */
class LoginRequest extends FormRequest
{
    private const MAKSIMUM_PERCOBAAN = 5;

    private const DURASI_KUNCI_DETIK = 60;

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->pastikanBelumTerkunci();

        if (! Auth::guard('web')->attempt($this->only('email', 'password'), $this->boolean('ingat_saya'))) {
            RateLimiter::hit($this->kunciPembatas(), self::DURASI_KUNCI_DETIK);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah.',
            ]);
        }

        // Akun nonaktif tidak boleh masuk meskipun kata sandinya benar.
        // Pemeriksaan dilakukan setelah attempt() agar pesan kesalahan tidak
        // membocorkan status akun kepada orang yang menebak kata sandi.
        if (Auth::guard('web')->user()?->status_akun !== StatusAkun::Aktif) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda dinonaktifkan. Hubungi admin untuk informasi lebih lanjut.',
            ]);
        }

        RateLimiter::clear($this->kunciPembatas());
    }

    /**
     * @throws ValidationException
     */
    private function pastikanBelumTerkunci(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciPembatas(), self::MAKSIMUM_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        $detik = RateLimiter::availableIn($this->kunciPembatas());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    private function kunciPembatas(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'kata sandi',
        ];
    }
}
