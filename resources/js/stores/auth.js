import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api, { csrf } from '../api';

export const useAuth = defineStore('auth', () => {
    const pengguna = ref(null);
    const sudahDiperiksa = ref(false);

    const masuk = computed(() => pengguna.value !== null);
    const peran = computed(() => pengguna.value?.role ?? null);
    const adalahPemilik = computed(() => peran.value === 'pemilik');
    const adalahAdmin = computed(() => peran.value === 'admin');
    const adalahPenyewa = computed(() => peran.value === 'penyewa');

    /**
     * Memeriksa sesi yang mungkin masih berjalan dari kunjungan sebelumnya.
     *
     * Dipanggil sekali saat aplikasi dimuat. Jawaban 401 di sini bukan galat
     * melainkan keadaan normal — artinya pengunjung belum masuk.
     */
    async function periksaSesi() {
        if (sudahDiperiksa.value) {
            return;
        }

        try {
            const { data } = await api.get('/saya');
            pengguna.value = data.data;
        } catch {
            pengguna.value = null;
        } finally {
            sudahDiperiksa.value = true;
        }
    }

    async function login(kredensial) {
        await csrf();
        const { data } = await api.post('/login', kredensial);
        pengguna.value = data.data;
        sudahDiperiksa.value = true;

        return pengguna.value;
    }

    async function registrasi(formulir) {
        await csrf();
        const { data } = await api.post('/registrasi', formulir);
        pengguna.value = data.data;
        sudahDiperiksa.value = true;

        return pengguna.value;
    }

    async function logout() {
        try {
            await api.post('/logout');
        } finally {
            // Keadaan lokal dibersihkan apa pun jawaban server. Bila
            // permintaan gagal karena sesi sudah tidak berlaku, pengguna
            // tetap harus terlihat keluar di layar.
            pengguna.value = null;
        }
    }

    return {
        pengguna,
        sudahDiperiksa,
        masuk,
        peran,
        adalahPemilik,
        adalahAdmin,
        adalahPenyewa,
        periksaSesi,
        login,
        registrasi,
        logout,
    };
});
