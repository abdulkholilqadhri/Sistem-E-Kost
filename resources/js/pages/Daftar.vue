<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { galatKolom, pesanGalat } from '../api';
import { useAuth } from '../stores/auth';

const auth = useAuth();
const router = useRouter();

const formulir = reactive({
    name: '',
    email: '',
    no_hp: '',
    password: '',
    password_confirmation: '',
});

const galat = ref(null);
const galatPerKolom = ref({});
const mengirim = ref(false);

async function kirim() {
    mengirim.value = true;
    galat.value = null;
    galatPerKolom.value = {};

    try {
        await auth.registrasi(formulir);
        router.push({ name: 'beranda' });
    } catch (error) {
        galat.value = pesanGalat(error, 'Pendaftaran gagal.');
        galatPerKolom.value = galatKolom(error);
    } finally {
        mengirim.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-sm px-4 py-12">
        <h1 class="text-2xl font-semibold tracking-tight">Daftar</h1>

        <!--
            Pendaftaran tidak menanyakan peran. Semua akun baru menjadi calon
            penyewa; menjadi pemilik kos ditempuh lewat pengajuan yang
            diverifikasi admin. Bila pilihan peran ditaruh di sini, verifikasi
            admin kehilangan seluruh maknanya.
        -->
        <p class="mt-1 text-sm text-stone-600">
            Punya kos dan ingin menyewakannya? Daftar dulu di sini, lalu ajukan diri
            sebagai pemilik kos dari menu setelah masuk.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="kirim">
            <p v-if="galat" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                {{ galat }}
            </p>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Nama lengkap</span>
                <input
                    v-model="formulir.name"
                    type="text"
                    required
                    autocomplete="name"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Email</span>
                <input
                    v-model="formulir.email"
                    type="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                    :class="galatPerKolom.email && 'border-red-400'"
                >
                <span v-if="galatPerKolom.email" class="mt-1 block text-xs text-red-700">
                    {{ galatPerKolom.email[0] }}
                </span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">
                    Nomor HP <span class="font-normal text-stone-500">(opsional)</span>
                </span>
                <input
                    v-model="formulir.no_hp"
                    type="tel"
                    autocomplete="tel"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Kata sandi</span>
                <input
                    v-model="formulir.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
                <span v-if="galatPerKolom.password" class="mt-1 block text-xs text-red-700">
                    {{ galatPerKolom.password[0] }}
                </span>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Ulangi kata sandi</span>
                <input
                    v-model="formulir.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
            </label>

            <button
                type="submit"
                class="w-full rounded-md bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                :disabled="mengirim"
            >
                {{ mengirim ? 'Memproses...' : 'Daftar' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            Sudah punya akun?
            <RouterLink :to="{ name: 'masuk' }" class="font-medium underline">Masuk</RouterLink>
        </p>
    </div>
</template>
