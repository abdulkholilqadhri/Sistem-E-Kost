<script setup>
import { onMounted, reactive, ref } from 'vue';
import api, { csrf, pesanGalat } from '../api';
import { useAuth } from '../stores/auth';

const auth = useAuth();

const riwayat = ref([]);
const memuat = ref(true);
const galat = ref(null);
const mengirim = ref(false);
const berhasil = ref(false);

const formulir = reactive({
    nama_lengkap: '',
    alamat: '',
    ktp: null,
    dokumen_kepemilikan: null,
});

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/pengajuan-pemilik');
        riwayat.value = data.data;
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat pengajuan.');
    } finally {
        memuat.value = false;
    }
}

async function kirim() {
    mengirim.value = true;
    galat.value = null;

    try {
        await csrf();

        const muatan = new FormData();
        muatan.append('nama_lengkap', formulir.nama_lengkap);
        muatan.append('ktp', formulir.ktp);

        if (formulir.alamat) {
            muatan.append('alamat', formulir.alamat);
        }

        if (formulir.dokumen_kepemilikan) {
            muatan.append('dokumen_kepemilikan', formulir.dokumen_kepemilikan);
        }

        await api.post('/pengajuan-pemilik', muatan);
        berhasil.value = true;
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Pengajuan gagal dikirim.');
    } finally {
        mengirim.value = false;
    }
}

function waktu(iso) {
    return iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <h1 v-muncul:naik class="text-2xl font-semibold tracking-tight">Jadi Pemilik Kos</h1>

        <p v-if="auth.adalahPemilik" class="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900">
            Akun Anda sudah terverifikasi sebagai pemilik kos.
        </p>

        <template v-else>
            <p v-muncul="{ nama: 'naik', jeda: 90 }" class="mt-2 text-sm leading-relaxed text-stone-600">
                Unggah KTP untuk diverifikasi admin. Berkas identitas Anda disimpan terpisah
                dari berkas publik dan hanya dapat dibuka admin yang memverifikasi — tidak
                muncul di manapun pada situs ini.
            </p>

            <p v-if="galat" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                {{ galat }}
            </p>

            <div v-if="berhasil" class="mt-4 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">
                Pengajuan terkirim. Status akan muncul di bawah setelah admin memeriksanya.
            </div>

            <form
                v-else
                v-muncul="{ nama: 'naik', jeda: 170 }"
                class="mt-6 space-y-4"
                @submit.prevent="kirim"
            >
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Nama sesuai KTP</span>
                    <input
                        v-model="formulir.nama_lengkap"
                        type="text"
                        required
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">
                        Alamat <span class="font-normal text-stone-500">(opsional)</span>
                    </span>
                    <textarea
                        v-model="formulir.alamat"
                        rows="2"
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    />
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Foto/scan KTP</span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        required
                        class="w-full text-sm"
                        @change="formulir.ktp = $event.target.files?.[0] ?? null"
                    >
                    <span class="mt-1 block text-xs text-stone-500">JPG, PNG, atau PDF. Maksimal 4 MB.</span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">
                        Dokumen kepemilikan <span class="font-normal text-stone-500">(opsional)</span>
                    </span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="w-full text-sm"
                        @change="formulir.dokumen_kepemilikan = $event.target.files?.[0] ?? null"
                    >
                </label>

                <button
                    type="submit"
                    class="rounded-md bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                    :disabled="mengirim"
                >
                    {{ mengirim ? 'Mengirim...' : 'Kirim Pengajuan' }}
                </button>
            </form>
        </template>

        <section v-if="!memuat && riwayat.length" class="mt-8">
            <h2 class="mb-3 font-semibold">Riwayat pengajuan</h2>
            <ul class="space-y-3">
                <li
                    v-for="(pengajuan, urutan) in riwayat"
                    :key="pengajuan.id"
                    v-muncul="{ nama: 'kiri', jeda: Math.min(urutan, 7) * 70 }"
                    class="rounded-lg border border-stone-200 bg-white p-3 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ pengajuan.nama_lengkap }}</span>
                        <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs">
                            {{ pengajuan.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-stone-500">Diajukan {{ waktu(pengajuan.diajukan_pada) }}</p>
                    <p v-if="pengajuan.catatan_admin" class="mt-1 text-red-800">
                        {{ pengajuan.catatan_admin }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
