<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { galatKolom, pesanGalat } from '../api';
import { useAuth } from '../stores/auth';

/**
 * Halaman masuk dan daftar dengan panel geser.
 *
 * Satu komponen melayani dua rute, /masuk dan /daftar. Yang membedakan hanya
 * panel mana yang terbuka saat halaman dibuka, sehingga seluruh tautan lama
 * ke kedua alamat itu tetap bekerja dan alamat di bilah peramban tetap jujur
 * menyatakan apa yang sedang ditampilkan.
 *
 * Perpindahan panel memakai keadaan reaktif Vue, bukan classList pada elemen
 * DOM seperti pada contoh aslinya. Mencari elemen lewat getElementById di
 * dalam komponen Vue mudah mengacu ke elemen yang sudah dibongkar saat
 * komponen berpindah, dan kesalahannya baru muncul setelah pengguna
 * bolak-balik beberapa kali.
 */
const auth = useAuth();
const router = useRouter();
const route = useRoute();

const panelKananAktif = ref(route.name === 'daftar');

const formMasuk = reactive({ email: '', password: '' });
const formDaftar = reactive({
    name: '',
    email: '',
    no_hp: '',
    password: '',
    password_confirmation: '',
});

const galatMasuk = ref(null);
const galatDaftar = ref(null);
const galatKolomDaftar = ref({});
const mengirim = ref(false);

function keDaftar() {
    panelKananAktif.value = true;
    router.replace({ name: 'daftar' });
}

function keMasuk() {
    panelKananAktif.value = false;
    router.replace({ name: 'masuk' });
}

async function kirimMasuk() {
    mengirim.value = true;
    galatMasuk.value = null;

    try {
        await auth.login(formMasuk);
        router.push(route.query.lanjut ?? { name: 'beranda' });
    } catch (error) {
        galatMasuk.value = pesanGalat(error, 'Gagal masuk.');
    } finally {
        mengirim.value = false;
    }
}

async function kirimDaftar() {
    mengirim.value = true;
    galatDaftar.value = null;
    galatKolomDaftar.value = {};

    try {
        await auth.registrasi(formDaftar);
        router.push({ name: 'beranda' });
    } catch (error) {
        galatDaftar.value = pesanGalat(error, 'Pendaftaran gagal.');
        galatKolomDaftar.value = galatKolom(error);
    } finally {
        mengirim.value = false;
    }
}

// Tombol maju-mundur peramban mengubah rute tanpa melewati keDaftar/keMasuk.
watch(() => route.name, (nama) => {
    panelKananAktif.value = nama === 'daftar';
});

onMounted(() => {
    panelKananAktif.value = route.name === 'daftar';
});
</script>

<template>
    <div class="halaman-auth">
        <!--
            Jalan keluar dari halaman ini.
            Orang yang membuka halaman masuk lalu berubah pikiran tidak boleh
            terjebak: menekan tombol kembali peramban bisa saja membawanya ke
            situs lain bila halaman ini yang pertama ia buka. Lebarnya
            disamakan dengan kartu di bawahnya supaya tepi kirinya sejajar.
        -->
        <div v-muncul:naik class="atas-auth">
            <RouterLink :to="{ name: 'beranda' }" class="tautan-kembali">
                <i class="pi pi-arrow-left" aria-hidden="true" />
                Kembali ke beranda
            </RouterLink>
        </div>

        <img
            v-muncul="{ nama: 'mekar', jeda: 90 }"
            src="/logo-ekost.png"
            alt="E-Kost"
            class="lambang-auth"
            width="150"
            height="130"
        >

        <!-- Kartunya naik sekali saat halaman dibuka. Tirai yang bergeser
             antara Masuk dan Daftar adalah animasi lain yang menjawab klik
             pengguna, dan keduanya tidak bertabrakan: yang satu sekali di
             awal, yang satu setiap kali panelnya ditukar. -->
        <div
            v-muncul="{ nama: 'naik', jeda: 180 }"
            class="kotak"
            :class="{ 'panel-kanan-aktif': panelKananAktif }"
        >
            <div class="wadah-form wadah-daftar">
                <form novalidate @submit.prevent="kirimDaftar">
                    <h1>Buat akun</h1>

                    <!--
                        Kalimat ini mengerjakan sesuatu, bukan sekadar
                        mengisi ruang: di sinilah orang mencari pilihan
                        "daftar sebagai pemilik kos", dan di sinilah
                        penjelasannya harus ada.
                    -->
                    <span>Semua akun baru dimulai sebagai calon penyewa</span>

                    <p v-if="galatDaftar" class="galat">{{ galatDaftar }}</p>

                    <input
                        v-model="formDaftar.name"
                        type="text"
                        placeholder="Nama lengkap"
                        autocomplete="name"
                        required
                    >
                    <input
                        v-model="formDaftar.email"
                        type="email"
                        placeholder="Email"
                        autocomplete="email"
                        required
                    >
                    <input
                        v-model="formDaftar.no_hp"
                        type="tel"
                        placeholder="Nomor HP (opsional)"
                        autocomplete="tel"
                    >
                    <input
                        v-model="formDaftar.password"
                        type="password"
                        placeholder="Kata sandi"
                        autocomplete="new-password"
                        required
                    >
                    <input
                        v-model="formDaftar.password_confirmation"
                        type="password"
                        placeholder="Ulangi kata sandi"
                        autocomplete="new-password"
                        required
                    >

                    <button type="submit" :disabled="mengirim">
                        {{ mengirim ? 'Memproses' : 'Daftar' }}
                    </button>
                </form>
            </div>

            <div class="wadah-form wadah-masuk">
                <form novalidate @submit.prevent="kirimMasuk">
                    <h1>Masuk</h1>
                    <span>Lanjutkan pencarian kos Anda</span>

                    <p v-if="galatMasuk" class="galat">{{ galatMasuk }}</p>

                    <input
                        v-model="formMasuk.email"
                        type="email"
                        placeholder="Email"
                        autocomplete="email"
                        required
                    >
                    <input
                        v-model="formMasuk.password"
                        type="password"
                        placeholder="Kata sandi"
                        autocomplete="current-password"
                        required
                    >

                    <button type="submit" :disabled="mengirim">
                        {{ mengirim ? 'Memproses' : 'Masuk' }}
                    </button>
                </form>
            </div>

            <div class="wadah-tirai" aria-hidden="true">
                <div class="tirai">
                    <div class="panel-tirai panel-kiri">
                        <h1>Sudah punya akun?</h1>
                        <p>Masuk untuk melihat pesanan dan melanjutkan pencarian kos Anda.</p>
                        <button type="button" class="hantu" @click="keMasuk">Masuk</button>
                    </div>

                    <div class="panel-tirai panel-kanan">
                        <h1>Baru di E-Kost?</h1>
                        <p>Daftar dulu untuk memesan kos di sekitar kampus.</p>
                        <button type="button" class="hantu" @click="keDaftar">Daftar</button>
                    </div>
                </div>
            </div>
        </div>

        <!--
            Penggantian form untuk layar kecil.
            Panel geser butuh dua kolom berdampingan; di ponsel ruangnya tidak
            ada, sehingga tirainya disembunyikan dan penggantinya tautan teks
            biasa. Tanpa ini, pengguna ponsel tidak punya cara apa pun
            berpindah antara masuk dan daftar.
        -->
        <p class="ganti-ponsel">
            <template v-if="panelKananAktif">
                Sudah punya akun?
                <button type="button" @click="keMasuk">Masuk</button>
            </template>
            <template v-else>
                Belum punya akun?
                <button type="button" @click="keDaftar">Daftar</button>
            </template>
        </p>
    </div>
</template>

<style scoped>
/*
 * Seluruh aturan di sini bersifat scoped. Contoh aslinya menyetel body, h1,
 * p, span, a, button, form, dan input secara global — dipasang apa adanya,
 * ia akan mengubah setiap tombol dan setiap input di seluruh aplikasi,
 * termasuk form tambah kos dan dashboard admin.
 *
 * Bentuk, ukuran, dan geraknya tetap sama persis dengan aslinya. Yang
 * berubah hanya warnanya — dari oranye-merah menjadi palet Mandar yang
 * dipakai seluruh aplikasi ini — dan hurufnya, yang memakai keluarga yang
 * sudah ada alih-alih menambah Montserrat sebagai huruf ketiga.
 */

.halaman-auth {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    /* Dikurangi ruang bilah navigasi melayang yang sudah diberikan App.vue,
       supaya halaman ini tidak menghasilkan gulir vertikal yang tidak perlu. */
    min-height: calc(100vh - 11rem);
    padding: 2rem 1rem 3rem;
}

.atas-auth {
    width: 768px;
    max-width: 100%;
    margin-bottom: 1rem;
}

.tautan-kembali {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.875rem;
    color: rgba(22, 35, 63, 0.65);
    transition: color 150ms ease, transform 150ms ease;
}

.tautan-kembali:hover {
    color: var(--color-indigo);
}

.tautan-kembali i {
    font-size: 0.7rem;
    transition: transform 150ms ease;
}

.tautan-kembali:hover i {
    transform: translateX(-2px);
}

.lambang-auth {
    width: 150px;
    height: auto;
    margin-bottom: 1.25rem;
}

.kotak {
    background-color: #fff;
    border-radius: 10px;
    box-shadow:
        0 14px 28px rgba(22, 35, 63, 0.22),
        0 10px 10px rgba(22, 35, 63, 0.18);
    position: relative;
    overflow: hidden;
    width: 768px;
    max-width: 100%;
    min-height: 520px;
}

.wadah-form {
    position: absolute;
    top: 0;
    height: 100%;
    transition: all 0.6s ease-in-out;
}

.wadah-masuk {
    left: 0;
    width: 50%;
    z-index: 2;
}

.kotak.panel-kanan-aktif .wadah-masuk {
    transform: translateX(100%);
}

.wadah-daftar {
    left: 0;
    width: 50%;
    opacity: 0;
    z-index: 1;
}

.kotak.panel-kanan-aktif .wadah-daftar {
    transform: translateX(100%);
    opacity: 1;
    z-index: 5;
    animation: tampil 0.6s;
}

@keyframes tampil {
    0%, 49.99% {
        opacity: 0;
        z-index: 1;
    }

    50%, 100% {
        opacity: 1;
        z-index: 5;
    }
}

.wadah-form form {
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 0 3rem;
    height: 100%;
    text-align: center;
}

.wadah-form h1 {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0;
}

.wadah-form span {
    font-size: 0.8125rem;
    color: rgba(22, 35, 63, 0.6);
    margin: 0.75rem 0 1.25rem;
    line-height: 1.4;
}

.wadah-form input {
    background-color: var(--color-kertas);
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.75rem 0.9375rem;
    margin: 0.4rem 0;
    width: 100%;
    font-size: 0.9375rem;
    transition: border-color 150ms ease, background-color 150ms ease;
}

.wadah-form input:focus {
    outline: none;
    background-color: #fff;
    border-color: var(--color-indigo-muda);
}

.wadah-form button {
    border-radius: 999px;
    border: 1px solid var(--color-indigo);
    background-color: var(--color-indigo);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.8rem 2.8rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 1.25rem;
    transition: transform 80ms ease-in, background-color 150ms ease;
}

.wadah-form button:hover:not(:disabled) {
    background-color: var(--color-indigo-muda);
}

.wadah-form button:active:not(:disabled) {
    transform: scale(0.95);
}

.wadah-form button:disabled {
    opacity: 0.65;
}

.galat {
    width: 100%;
    margin: 0 0 0.5rem;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    background-color: rgba(158, 43, 43, 0.1);
    color: var(--color-maroon);
    font-size: 0.8125rem;
    line-height: 1.35;
    text-align: left;
}

.wadah-tirai {
    position: absolute;
    top: 0;
    left: 50%;
    width: 50%;
    height: 100%;
    overflow: hidden;
    transition: transform 0.6s ease-in-out;
    z-index: 100;
}

.kotak.panel-kanan-aktif .wadah-tirai {
    transform: translateX(-100%);
}

.tirai {
    /* Gradien aslinya oranye-merah. Diganti ke maroon dan indigo supaya
       halaman ini tidak terlihat berasal dari aplikasi yang berbeda. */
    background: linear-gradient(120deg, var(--color-maroon), var(--color-indigo) 85%);
    color: #fff;
    position: relative;
    left: -100%;
    height: 100%;
    width: 200%;
    transform: translateX(0);
    transition: transform 0.6s ease-in-out;
}

.kotak.panel-kanan-aktif .tirai {
    transform: translateX(50%);
}

.panel-tirai {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 0 2.5rem;
    text-align: center;
    top: 0;
    height: 100%;
    width: 50%;
    transform: translateX(0);
    transition: transform 0.6s ease-in-out;
}

.panel-tirai h1 {
    font-family: var(--font-display);
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0;
}

.panel-tirai p {
    font-size: 0.875rem;
    line-height: 1.45;
    letter-spacing: 0.3px;
    margin: 1.25rem 0 1.875rem;
    color: rgba(255, 255, 255, 0.85);
}

.panel-tirai button.hantu {
    border-radius: 999px;
    border: 1px solid #fff;
    background-color: transparent;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.8rem 2.8rem;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: transform 80ms ease-in, background-color 150ms ease;
}

.panel-tirai button.hantu:hover {
    background-color: rgba(255, 255, 255, 0.14);
}

.panel-tirai button.hantu:active {
    transform: scale(0.95);
}

.panel-kiri {
    transform: translateX(-20%);
}

.kotak.panel-kanan-aktif .panel-kiri {
    transform: translateX(0);
}

.panel-kanan {
    right: 0;
    transform: translateX(0);
}

.kotak.panel-kanan-aktif .panel-kanan {
    transform: translateX(20%);
}

.ganti-ponsel {
    display: none;
    margin-top: 1.5rem;
    font-size: 0.875rem;
    color: rgba(22, 35, 63, 0.7);
}

.ganti-ponsel button {
    font-weight: 600;
    color: var(--color-indigo);
    text-decoration: underline;
}

/*
 * Di bawah 768 piksel, dua kolom berdampingan tidak muat. Tirainya
 * disembunyikan, form ditampilkan bergantian memakai lebar penuh, dan
 * perpindahannya lewat tautan teks di bawah kartu.
 */
@media (max-width: 767px) {
    .kotak {
        min-height: 0;
        width: 100%;
    }

    .wadah-tirai {
        display: none;
    }

    .wadah-form {
        position: static;
        width: 100%;
        opacity: 1;
        transform: none !important;
        animation: none !important;
    }

    .wadah-daftar {
        display: none;
    }

    .kotak.panel-kanan-aktif .wadah-daftar {
        display: block;
    }

    .kotak.panel-kanan-aktif .wadah-masuk {
        display: none;
    }

    .wadah-form form {
        padding: 2.5rem 1.5rem;
        height: auto;
    }

    .ganti-ponsel {
        display: block;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tautan-kembali,
    .tautan-kembali i,
    .wadah-form,
    .wadah-tirai,
    .tirai,
    .panel-tirai {
        transition: none;
    }

    .kotak.panel-kanan-aktif .wadah-daftar {
        animation: none;
    }
}
</style>
