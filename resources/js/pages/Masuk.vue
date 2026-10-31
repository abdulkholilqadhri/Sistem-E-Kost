<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { galatKolom, pesanGalat } from '../api';
import { useAuth } from '../stores/auth';

const auth = useAuth();
const router = useRouter();
const route = useRoute();

const formulir = reactive({ email: '', password: '' });
const galat = ref(null);
const galatPerKolom = ref({});
const mengirim = ref(false);

async function kirim() {
    mengirim.value = true;
    galat.value = null;
    galatPerKolom.value = {};

    try {
        await auth.login(formulir);
        router.push(route.query.lanjut ?? { name: 'beranda' });
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal masuk.');
        galatPerKolom.value = galatKolom(error);
    } finally {
        mengirim.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-sm px-4 py-12">
        <h1 class="text-2xl font-semibold tracking-tight">Masuk</h1>
        <p class="mt-1 text-sm text-stone-600">Masuk untuk memesan kos dan mengelola pesanan Anda.</p>

        <form class="mt-6 space-y-4" @submit.prevent="kirim">
            <p v-if="galat" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                {{ galat }}
            </p>

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
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Kata sandi</span>
                <input
                    v-model="formulir.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
            </label>

            <button
                type="submit"
                class="w-full rounded-md bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                :disabled="mengirim"
            >
                {{ mengirim ? 'Memproses...' : 'Masuk' }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-stone-600">
            Belum punya akun?
            <RouterLink :to="{ name: 'daftar' }" class="font-medium underline">Daftar di sini</RouterLink>
        </p>
    </div>
</template>
