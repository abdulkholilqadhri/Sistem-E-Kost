import { createRouter, createWebHistory } from 'vue-router';
import { mulaiMuat, selesaiMuat } from '../indikatorMuat';
import { useAuth } from '../stores/auth';
import CangkangPeran from '../components/CangkangPeran.vue';

/*
 * Dua kelompok rute dengan dua tata letak, mengikuti SDD BAB IV.
 *
 * Kelompok pertama adalah halaman publik: beranda, detail kos, autentikasi.
 * Semuanya memakai bilah navigasi atas seperti pada mockup pencarian.
 *
 * Kelompok kedua bersarang di dalam CangkangPeran — sidebar kiri seperti
 * pada mockup pembayaran dan dasbor admin. Cangkangnya dimuat biasa, bukan
 * malas: ia dipakai semua halaman yang memerlukan login, sehingga memecahnya
 * ke berkas terpisah hanya menambah satu permintaan tanpa menghemat apa pun.
 *
 * meta diwariskan dari induk ke anak, jadi cangkang: true dan
 * perluMasuk: true cukup ditulis sekali di induknya.
 */
const rute = [
    {
        path: '/',
        name: 'beranda',
        component: () => import('../pages/Beranda.vue'),
    },
    {
        path: '/kos/:id',
        name: 'detail-kos',
        component: () => import('../pages/DetailKos.vue'),
        props: true,
    },
    /*
     * Dua rute, satu komponen. Yang membedakan hanya panel mana yang terbuka.
     *
     * meta.kunci menyamakan kunci RouterView untuk keduanya, sehingga Vue
     * memakai ulang komponen yang sama saat pengguna berpindah antara masuk
     * dan daftar. Tanpa itu, komponennya dibongkar dan dipasang ulang setiap
     * kali — dan animasi panel gesernya tidak akan pernah terlihat.
     */
    {
        path: '/masuk',
        name: 'masuk',
        component: () => import('../pages/Autentikasi.vue'),
        meta: { tamuSaja: true, kunci: 'auth' },
    },
    {
        path: '/daftar',
        name: 'daftar',
        component: () => import('../pages/Autentikasi.vue'),
        meta: { tamuSaja: true, kunci: 'auth' },
    },
    /*
     * Area yang memerlukan login. Seluruhnya memakai cangkang sidebar.
     *
     * meta.kunci disamakan untuk semua anaknya supaya RouterView di App.vue
     * tidak membongkar-pasang cangkangnya setiap kali berpindah halaman di
     * dalamnya — kalau itu terjadi, sidebar akan berkedip dan laci yang
     * sedang terbuka akan tertutup sendiri.
     *
     * meta.judul mengisi remah-remah di bilah atas cangkang.
     */
    {
        path: '/',
        component: CangkangPeran,
        meta: { perluMasuk: true, cangkang: true, kunci: 'cangkang' },
        children: [
            {
                path: 'dashboard',
                name: 'dashboard',
                component: () => import('../pages/Dashboard.vue'),
                meta: { judul: 'Dashboard' },
            },
            {
                path: 'pesanan-saya',
                name: 'pesanan-saya',
                component: () => import('../pages/PesananSaya.vue'),
                meta: { judul: 'Pemesanan Saya' },
            },
            {
                path: 'pembayaran',
                name: 'pembayaran',
                component: () => import('../pages/Pembayaran.vue'),
                meta: { judul: 'Pembayaran' },
            },
            {
                path: 'pembayaran/:id',
                name: 'pembayaran-detail',
                component: () => import('../pages/PembayaranDetail.vue'),
                props: true,
                meta: { judul: 'Pembayaran' },
            },
            {
                path: 'jadi-pemilik',
                name: 'jadi-pemilik',
                component: () => import('../pages/JadiPemilik.vue'),
                meta: { judul: 'Jadi Pemilik' },
            },

            // Pemilik kos
            {
                path: 'pemilik/kos',
                name: 'pemilik-kos',
                component: () => import('../pages/pemilik/KosSaya.vue'),
                meta: { peran: ['pemilik', 'admin'], judul: 'Kos Saya' },
            },
            {
                path: 'pemilik/kos/baru',
                name: 'pemilik-kos-baru',
                component: () => import('../pages/pemilik/FormKos.vue'),
                meta: { peran: ['pemilik', 'admin'], judul: 'Tambah Kos' },
            },
            {
                path: 'pemilik/kos/:id/ubah',
                name: 'pemilik-kos-ubah',
                component: () => import('../pages/pemilik/FormKos.vue'),
                props: true,
                meta: { peran: ['pemilik', 'admin'], judul: 'Ubah Data Kos' },
            },
            {
                path: 'pemilik/pesanan',
                name: 'pemilik-pesanan',
                component: () => import('../pages/pemilik/PesananMasuk.vue'),
                meta: { peran: ['pemilik', 'admin'], judul: 'Pesanan Masuk' },
            },

            // Admin
            {
                path: 'admin/pengguna',
                name: 'admin-pengguna',
                component: () => import('../pages/admin/KelolaPengguna.vue'),
                meta: { peran: ['admin'], judul: 'Kelola Pengguna' },
            },
            {
                path: 'admin/pengajuan',
                name: 'admin-pengajuan',
                component: () => import('../pages/admin/VerifikasiPengajuan.vue'),
                meta: { peran: ['admin'], judul: 'Verifikasi Pengajuan' },
            },
        ],
    },

    {
        path: '/:pathMatch(.*)*',
        name: 'tidak-ditemukan',
        component: () => import('../pages/TidakDitemukan.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes: rute,
    scrollBehavior(to, from, posisiTersimpan) {
        return posisiTersimpan ?? { top: 0 };
    },
});

/**
 * Penjaga rute ini hanya mengatur tampilan, bukan keamanan.
 *
 * Seseorang yang mengubah JavaScript di peramban dapat membuka halaman mana
 * pun di sini, termasuk halaman admin. Yang benar-benar melindungi data adalah
 * middleware role dan Policy di sisi server; penjaga ini sekadar mencegah
 * pengguna tersesat ke halaman yang datanya pasti akan ditolak server.
 */
router.beforeEach(async (to, from) => {
    // Perpindahan yang hanya mengubah bagian setelah tanda pagar tidak
    // memuat apa pun, jadi tidak perlu indikator.
    if (to.path !== from.path) {
        mulaiMuat();
    }

    const auth = useAuth();

    await auth.periksaSesi();

    if (to.meta.perluMasuk && !auth.masuk) {
        return { name: 'masuk', query: { lanjut: to.fullPath } };
    }

    if (to.meta.tamuSaja && auth.masuk) {
        return { name: 'beranda' };
    }

    if (to.meta.peran && !to.meta.peran.includes(auth.peran)) {
        return { name: 'beranda' };
    }

    return true;
});

/*
 * Indikator ditutup pada afterEach dan onError.
 *
 * afterEach juga berjalan ketika perpindahan dibatalkan oleh pengalihan di
 * beforeEach — misalnya pengunjung yang belum masuk diarahkan ke halaman
 * Masuk. Tanpa menutupnya di sini, batangnya akan menggantung selamanya pada
 * kasus itu.
 */
router.afterEach(() => selesaiMuat());
router.onError(() => selesaiMuat());

export default router;
