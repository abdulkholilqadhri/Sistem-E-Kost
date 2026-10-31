<?php

namespace App\Services;

use App\Enums\RoleUser;
use App\Enums\StatusPengajuan;
use App\Models\PengajuanPemilik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Logika pengajuan menjadi pemilik kos (FR-14 dan FR-15).
 *
 * Diletakkan pada service, bukan pada model maupun controller, karena
 * persetujuan menyentuh dua tabel sekaligus: status pengajuan dan peran
 * pengguna. Keduanya harus berubah bersama-sama atau tidak sama sekali.
 */
class PengajuanPemilikService
{
    private const FOLDER_KTP = 'pengajuan-pemilik/ktp';

    private const FOLDER_KEPEMILIKAN = 'pengajuan-pemilik/kepemilikan';

    public function ajukan(
        User $pemohon,
        string $namaLengkap,
        ?string $alamat,
        UploadedFile $ktp,
        ?UploadedFile $dokumenKepemilikan = null,
    ): PengajuanPemilik {
        if ($this->punyaPengajuanTertunda($pemohon)) {
            throw new RuntimeException('Anda masih memiliki pengajuan yang sedang diproses.');
        }

        $disk = config('ekost.disk_privat');

        // store() membangkitkan nama berkas acak dan mengabaikan nama asli
        // dari pengguna. Nama asli tidak pernah dipakai sebagai nama berkas
        // di server, sehingga tidak ada jalan menyisipkan path seperti
        // "../../public/skrip.php" melalui nama unggahan.
        $pathKtp = $ktp->store(self::FOLDER_KTP, $disk);

        if ($pathKtp === false) {
            throw new RuntimeException('Berkas KTP gagal disimpan.');
        }

        $pathKepemilikan = null;

        if ($dokumenKepemilikan !== null) {
            $pathKepemilikan = $dokumenKepemilikan->store(self::FOLDER_KEPEMILIKAN, $disk);

            if ($pathKepemilikan === false) {
                Storage::disk($disk)->delete($pathKtp);

                throw new RuntimeException('Dokumen kepemilikan gagal disimpan.');
            }
        }

        $pengajuan = new PengajuanPemilik([
            'nama_lengkap' => $namaLengkap,
            'alamat' => $alamat,
            'path_ktp' => $pathKtp,
            'path_dokumen_kepemilikan' => $pathKepemilikan,
        ]);

        $pengajuan->user_id = $pemohon->id;
        $pengajuan->status = StatusPengajuan::Menunggu;
        $pengajuan->save();

        return $pengajuan;
    }

    /**
     * Menyetujui pengajuan dan menaikkan peran pemohon menjadi Pemilik.
     *
     * Dua perubahan ini dibungkus satu transaksi. Bila penyimpanan peran gagal
     * setelah status pengajuan tersimpan, akan ada pengajuan berstatus
     * "disetujui" milik pengguna yang perannya masih penyewa — dan karena
     * pengajuan yang final tidak dapat diverifikasi ulang, orang itu
     * terjebak tanpa jalan keluar.
     */
    public function setujui(PengajuanPemilik $pengajuan, User $admin, ?string $catatan = null): PengajuanPemilik
    {
        return DB::transaction(function () use ($pengajuan, $admin, $catatan): PengajuanPemilik {
            $pengajuan->status = StatusPengajuan::Disetujui;
            $pengajuan->catatan_admin = $catatan;
            $pengajuan->diverifikasi_oleh = $admin->id;
            $pengajuan->diverifikasi_pada = now();
            $pengajuan->save();

            $pemohon = $pengajuan->user;
            $pemohon->role = RoleUser::Pemilik;
            $pemohon->save();

            return $pengajuan;
        });
    }

    /**
     * Menolak pengajuan. Peran pemohon tidak berubah dan berkasnya tetap
     * disimpan sebagai rekam jejak keputusan.
     */
    public function tolak(PengajuanPemilik $pengajuan, User $admin, string $catatan): PengajuanPemilik
    {
        $pengajuan->status = StatusPengajuan::Ditolak;
        $pengajuan->catatan_admin = $catatan;
        $pengajuan->diverifikasi_oleh = $admin->id;
        $pengajuan->diverifikasi_pada = now();
        $pengajuan->save();

        return $pengajuan;
    }

    public function punyaPengajuanTertunda(User $pemohon): bool
    {
        return PengajuanPemilik::query()
            ->where('user_id', $pemohon->id)
            ->where('status', StatusPengajuan::Menunggu)
            ->exists();
    }
}
