import axios from 'axios';

/**
 * Klien HTTP untuk seluruh aplikasi.
 *
 * Autentikasi memakai cookie sesi, bukan token. Karena itu:
 *
 *   withCredentials: true  -> cookie ikut terkirim pada setiap permintaan
 *   X-Requested-With       -> membuat Laravel menjawab 401/419 sebagai JSON,
 *                             bukan mengalihkan ke halaman login
 *
 * Cookie XSRF-TOKEN dibaca otomatis oleh axios dan dikirim kembali sebagai
 * header X-XSRF-TOKEN. Itu sebabnya csrf() di bawah harus dipanggil sekali
 * sebelum permintaan POST pertama: tanpa cookie itu, Laravel menolak dengan
 * 419 dan pesannya tidak memberi petunjuk apa pun tentang sebabnya.
 */
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let csrfSudahDiambil = false;

export async function csrf() {
    if (csrfSudahDiambil) {
        return;
    }

    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    csrfSudahDiambil = true;
}

/**
 * Mengubah galat axios menjadi pesan yang layak ditampilkan.
 *
 * Laravel mengirim galat validasi sebagai objek errors berisi larik pesan per
 * kolom; sisanya mengirim pesan tunggal. Tanpa penerjemahan ini, antarmuka
 * akan menampilkan "[object Object]" kepada pengguna.
 */
export function pesanGalat(error, bawaan = 'Terjadi kesalahan. Coba lagi.') {
    const data = error?.response?.data;

    if (!data) {
        return navigator.onLine === false
            ? 'Tidak ada koneksi internet.'
            : bawaan;
    }

    if (data.errors) {
        return Object.values(data.errors).flat().join(' ');
    }

    return data.pesan ?? data.message ?? bawaan;
}

/**
 * Galat validasi per kolom, untuk ditempelkan di bawah masing-masing input.
 */
export function galatKolom(error) {
    return error?.response?.data?.errors ?? {};
}

export default api;
