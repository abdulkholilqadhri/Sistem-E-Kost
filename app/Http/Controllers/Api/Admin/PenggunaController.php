<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StatusAkun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UbahStatusAkunRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * FR-16 — Pengelolaan akun pengguna oleh admin.
 *
 * Yang dapat diubah hanyalah status aktif akun. Peran tidak dapat diubah dari
 * sini: kenaikan peran menjadi pemilik kos punya jalurnya sendiri lewat
 * pengajuan berkas dan verifikasi (FR-14 dan FR-15), dan menyediakan pintu
 * kedua yang melewati pemeriksaan KTP akan membuat jalur itu sia-sia.
 */
class PenggunaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $pengguna = User::query()
            ->when(
                $request->filled('role'),
                fn ($kueri) => $kueri->where('role', $request->string('role')->toString()),
            )
            ->when(
                $request->filled('status_akun'),
                fn ($kueri) => $kueri->where('status_akun', $request->string('status_akun')->toString()),
            )
            ->when(
                $request->filled('q'),
                function ($kueri) use ($request) {
                    $kata = $request->string('q')->toString();

                    $kueri->where(function ($dalam) use ($kata) {
                        $dalam->where('name', 'like', "%{$kata}%")
                            ->orWhere('email', 'like', "%{$kata}%");
                    });
                },
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($pengguna);
    }

    /**
     * Mengaktifkan atau menonaktifkan sebuah akun.
     *
     * status_akun bukan kolom fillable, jadi nilainya diberikan langsung —
     * bukan lewat update() dengan array. Itu disengaja: kalau suatu saat kolom
     * ini menjadi fillable, satu permintaan registrasi yang menyisipkan
     * "status_akun" sudah cukup untuk melewati penonaktifan.
     */
    public function ubahStatus(UbahStatusAkunRequest $request, User $pengguna): UserResource
    {
        $this->authorize('kelolaStatus', $pengguna);

        $status = $request->statusAkun();

        $pengguna->status_akun = $status;
        $pengguna->status_diubah_pada = now();

        // Alasan hanya melekat pada penonaktifan. Saat akun diaktifkan kembali,
        // alasan lama dibersihkan supaya tidak tertinggal dan terbaca seolah
        // akun yang sudah pulih itu masih bermasalah.
        $pengguna->alasan_nonaktif = $status === StatusAkun::Nonaktif
            ? $request->string('alasan')->toString()
            : null;

        $pengguna->save();

        if ($status === StatusAkun::Nonaktif) {
            /*
             * Token Sanctum dicabut agar klien yang memakai token — bukan
             * cookie sesi — ikut kehilangan akses seketika.
             *
             * Sesi peramban tidak perlu dihapus di sini: middleware akun.aktif
             * memeriksa status pada setiap permintaan dan akan mengeluarkan
             * pengguna itu sendiri pada permintaan berikutnya.
             */
            $pengguna->tokens()->delete();
        }

        return new UserResource($pengguna);
    }
}
