<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleUser;
use App\Enums\StatusKos;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPemesanan;
use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Kos;
use App\Models\Pembayaran;
use App\Models\Pemesanan;
use App\Models\PengajuanPemilik;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-03 — Dashboard.
 *
 * Satu endpoint untuk tiga peran. Isi jawabannya ditentukan oleh peran
 * pemanggil, bukan oleh parameter permintaan: kalau peran dikirim klien,
 * penyewa mana pun bisa meminta ringkasan admin hanya dengan mengubah satu
 * nilai di peramban.
 *
 * Bentuk jawabannya sengaja seragam — sebuah daftar `ringkasan` berisi
 * angka-angka beserta labelnya, ditambah `tindakan` berisi hal yang perlu
 * dikerjakan. Halaman Vue cukup merendernya apa adanya tanpa perlu tahu
 * peran siapa yang sedang dilihat.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $pengguna = $request->user();

        $isi = match ($pengguna->role) {
            RoleUser::Admin => $this->untukAdmin(),
            RoleUser::Pemilik => $this->untukPemilik($pengguna),
            RoleUser::Penyewa => $this->untukPenyewa($pengguna),
        };

        return response()->json([
            'data' => [
                'peran' => $pengguna->role->value,
                'peran_label' => $pengguna->role->label(),
                'nama' => $pengguna->name,
                ...$isi,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function untukPenyewa(User $pengguna): array
    {
        $pemesanan = Pemesanan::query()->where('penyewa_id', $pengguna->id);

        $perluBayar = (clone $pemesanan)
            ->where('status', StatusPemesanan::MenungguPembayaran)
            ->count();

        $belumDiulas = (clone $pemesanan)
            ->where('status', StatusPemesanan::Selesai)
            ->whereDoesntHave('ulasan')
            ->count();

        return [
            'ringkasan' => [
                $this->angka('Sewa aktif', (clone $pemesanan)->where('status', StatusPemesanan::Aktif)->count(), 'pi-home'),
                $this->angka('Menunggu konfirmasi', (clone $pemesanan)->where('status', StatusPemesanan::MenungguKonfirmasi)->count(), 'pi-clock'),
                $this->angka('Perlu dibayar', $perluBayar, 'pi-credit-card', $perluBayar > 0),
                $this->angka('Riwayat pesanan', (clone $pemesanan)->count(), 'pi-inbox'),
            ],
            'tindakan' => array_values(array_filter([
                $perluBayar > 0
                    ? $this->tindakan("$perluBayar pesanan menunggu pembayaran", 'Selesaikan sebelum batas waktunya habis agar kamar tidak dilepas.', 'pembayaran')
                    : null,
                $belumDiulas > 0
                    ? $this->tindakan("$belumDiulas sewa selesai belum diulas", 'Ulasan Anda membantu calon penyewa lain memilih.', 'pesanan-saya')
                    : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function untukPemilik(User $pengguna): array
    {
        $kos = Kos::query()->where('pemilik_id', $pengguna->id);

        $pemesanan = Pemesanan::query()
            ->whereHas('kos', fn ($kueri) => $kueri->where('pemilik_id', $pengguna->id));

        $perluKonfirmasi = (clone $pemesanan)
            ->where('status', StatusPemesanan::MenungguKonfirmasi)
            ->count();

        $perluVerifikasi = Pembayaran::query()
            ->where('status', StatusPembayaran::Menunggu)
            ->whereHas('pemesanan.kos', fn ($kueri) => $kueri->where('pemilik_id', $pengguna->id))
            ->count();

        return [
            'ringkasan' => [
                $this->angka('Kos tayang', (clone $kos)->where('status', StatusKos::Aktif)->count(), 'pi-building'),
                $this->angka('Kamar tersisa', (int) (clone $kos)->where('status', StatusKos::Aktif)->sum('sisa_kamar'), 'pi-key'),
                $this->angka('Perlu dikonfirmasi', $perluKonfirmasi, 'pi-inbox', $perluKonfirmasi > 0),
                $this->angka('Penyewa aktif', (clone $pemesanan)->where('status', StatusPemesanan::Aktif)->count(), 'pi-users'),
            ],
            'tindakan' => array_values(array_filter([
                $perluKonfirmasi > 0
                    ? $this->tindakan("$perluKonfirmasi pesanan menunggu keputusan", 'Penyewa tidak dapat membayar sebelum Anda menerima pesanannya.', 'pemilik-pesanan')
                    : null,
                $perluVerifikasi > 0
                    ? $this->tindakan("$perluVerifikasi bukti transfer belum diverifikasi", 'Sewa baru menjadi aktif setelah Anda memeriksa buktinya.', 'pemilik-pesanan')
                    : null,
                (clone $kos)->count() === 0
                    ? $this->tindakan('Anda belum menambahkan kos', 'Kos baru akan langsung tampil di peta setelah disimpan.', 'pemilik-kos-baru')
                    : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function untukAdmin(): array
    {
        $menunggu = PengajuanPemilik::query()
            ->where('status', StatusPengajuan::Menunggu)
            ->count();

        return [
            'ringkasan' => [
                $this->angka('Total pengguna', User::query()->count(), 'pi-users'),
                $this->angka('Pemilik kos', User::query()->where('role', RoleUser::Pemilik)->count(), 'pi-id-card'),
                $this->angka('Kos terdaftar', Kos::query()->count(), 'pi-building'),
                $this->angka('Total pemesanan', Pemesanan::query()->count(), 'pi-inbox'),
            ],
            'tindakan' => array_values(array_filter([
                $menunggu > 0
                    ? $this->tindakan("$menunggu pengajuan menunggu verifikasi", 'Pemohon tidak dapat mendaftarkan kos sebelum pengajuannya diperiksa.', 'admin-pengajuan')
                    : null,
            ])),

            /*
             * Antrean verifikasi ikut dikirim utuh, bukan hanya jumlahnya.
             * Mockup dasbor admin menampilkan tabel pemohon lengkap dengan
             * waktu pengajuannya, dan meminta halaman Vue mengambilnya lewat
             * permintaan kedua hanya akan membuat dasbor tampil separuh dulu.
             */
            'antrean' => PengajuanPemilik::query()
                ->where('status', StatusPengajuan::Menunggu)
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (PengajuanPemilik $pengajuan) => [
                    'id' => $pengajuan->id,
                    'nama_lengkap' => $pengajuan->nama_lengkap,
                    'pemohon' => $pengajuan->user?->name,
                    'punya_dokumen' => $pengajuan->path_dokumen_kepemilikan !== null,
                    'diajukan_pada' => $pengajuan->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function angka(string $label, int $nilai, string $ikon, bool $mendesak = false): array
    {
        return [
            'label' => $label,
            'nilai' => $nilai,
            'ikon' => $ikon,
            'mendesak' => $mendesak,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function tindakan(string $judul, string $keterangan, string $rute): array
    {
        return [
            'judul' => $judul,
            'keterangan' => $keterangan,
            'rute' => $rute,
        ];
    }
}
