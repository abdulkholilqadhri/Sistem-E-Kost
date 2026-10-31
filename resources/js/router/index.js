import { createRouter, createWebHistory } from 'vue-router';
import { mulaiMuat, selesaiMuat } from '../indikatorMuat';
import { useAuth } from '../stores/auth';

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
    {
        path: '/pesanan-saya',
        name: 'pesanan-saya',
        component: () => import('../pages/PesananSaya.vue'),
        meta: { perluMasuk: true },
    },
    {
        path: '/jadi-pemilik',
        name: 'jadi-pemilik',
        component: () => import('../pages/JadiPemilik.vue'),
        meta: { perluMasuk: true },
    },

    // Pemilik kos
    {
        path: '/pemilik/kos',
        name: 'pemilik-kos',
        component: () => import('../pages/pemilik/KosSaya.vue'),
        meta: { perluMasuk: true, peran: ['pemilik', 'admin'] },
    },
    {
        path: '/pemilik/kos/baru',
        name: 'pemilik-kos-baru',
        component: () => import('../pages/pemilik/FormKos.vue'),
        meta: { perluMasuk: true, peran: ['pemilik', 'admin'] },
    },
    {
        path: '/pemilik/kos/:id/ubah',
        name: 'pemilik-kos-ubah',
        component: () => import('../pages/pemilik/FormKos.vue'),
        props: true,
        meta: { perluMasuk: true, peran: ['pemilik', 'admin'] },
    },
    {
        path: '/pemilik/pesanan',
        name: 'pemilik-pesanan',
        component: () => import('../pages/pemilik/PesananMasuk.vue'),
        meta: { perluMasuk: true, peran: ['pemilik', 'admin'] },
    },

    // Admin
    {
        path: '/admin/pengajuan',
        name: 'admin-pengajuan',
        component: () => import('../pages/admin/VerifikasiPengajuan.vue'),
        meta: { perluMasuk: true, peran: ['admin'] },
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
