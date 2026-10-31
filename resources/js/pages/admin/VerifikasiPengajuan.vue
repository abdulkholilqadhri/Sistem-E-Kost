<script setup>
import { onMounted, reactive, ref } from 'vue';
import api, { csrf, pesanGalat } from '../../api';

const daftar = ref([]);
const memuat = ref(true);
const galat = ref(null);
const memproses = ref(null);
const catatan = reactive({});
const saringStatus = ref('menunggu');

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/admin/pengajuan-pemilik', {
            params: { status: saringStatus.value },
        });
        daftar.value = data.data;
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat pengajuan.');
    } finally {
        memuat.value = false;
    }
}

async function putuskan(pengajuan, keputusan) {
    if (keputusan === 'ditolak' && !catatan[pengajuan.id]) {
        galat.value = 'Isi alasan penolakan terlebih dahulu.';

        return;
    }

    memproses.value = pengajuan.id;
    galat.value = null;

    try {
        await csrf();
        await api.post(`/admin/pengajuan-pemilik/${pengajuan.id}/verifikasi`, {
            keputusan,
            catatan_admin: catatan[pengajuan.id] || undefined,
        });
        delete catatan[pengajuan.id];
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Keputusan gagal disimpan.');
    } finally {
        memproses.value = null;
    }
}

function waktu(iso) {
    return iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-3xl px-4 py-6">
        <h1 class="text-2xl font-semibold tracking-tight">Verifikasi Pengajuan Pemilik Kos</h1>
        <p class="mt-1 text-sm text-stone-600">
            Periksa berkas identitas pemohon sebelum menyetujui. Persetujuan mengubah
            peran akun menjadi pemilik kos dan tidak dapat dibatalkan dari halaman ini.
        </p>

        <div class="mt-4 flex gap-2">
            <button
                v-for="pilihan in [
                    { nilai: 'menunggu', label: 'Menunggu' },
                    { nilai: 'disetujui', label: 'Disetujui' },
                    { nilai: 'ditolak', label: 'Ditolak' },
                ]"
                :key="pilihan.nilai"
                type="button"
                class="rounded-full px-3 py-1 text-sm"
                :class="saringStatus === pilihan.nilai
                    ? 'bg-stone-900 text-white'
                    : 'border border-stone-300 hover:bg-stone-100'"
                @click="saringStatus = pilihan.nilai; muat()"
            >
                {{ pilihan.label }}
            </button>
        </div>

        <p v-if="galat" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            {{ galat }}
        </p>

        <p v-if="memuat" class="mt-6 text-sm text-stone-500">Memuat...</p>

        <p
            v-else-if="daftar.length === 0"
            class="mt-6 rounded-lg border border-stone-200 bg-white p-6 text-center text-sm text-stone-500"
        >
            Tidak ada pengajuan berstatus ini.
        </p>

        <ul v-else class="mt-6 space-y-4">
            <li
                v-for="pengajuan in daftar"
                :key="pengajuan.id"
                class="rounded-lg border border-stone-200 bg-white p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-medium">{{ pengajuan.nama_lengkap }}</h2>
                        <p class="text-sm text-stone-600">
                            Akun: {{ pengajuan.pemohon?.nama }} &middot; {{ pengajuan.pemohon?.email }}
                        </p>
                        <p v-if="pengajuan.alamat" class="text-sm text-stone-500">{{ pengajuan.alamat }}</p>
                        <p class="mt-1 text-sm text-stone-500">Diajukan {{ waktu(pengajuan.diajukan_pada) }}</p>
                    </div>

                    <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium">
                        {{ pengajuan.status_label }}
                    </span>
                </div>

                <!--
                    Berkas dibuka lewat endpoint ber-Policy, bukan lewat URL
                    berkas langsung. KTP memuat NIK dan tidak pernah berada di
                    direktori publik.
                -->
                <div class="mt-3 flex flex-wrap gap-3 text-sm">
                    <a
                        :href="`/api/admin/pengajuan-pemilik/${pengajuan.id}/dokumen/ktp`"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-100"
                    >
                        Buka KTP
                    </a>

                    <a
                        v-if="pengajuan.ada_dokumen_kepemilikan"
                        :href="`/api/admin/pengajuan-pemilik/${pengajuan.id}/dokumen/kepemilikan`"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-100"
                    >
                        Buka dokumen kepemilikan
                    </a>
                </div>

                <div v-if="pengajuan.status === 'menunggu'" class="mt-4 space-y-2 rounded-md bg-stone-50 p-3">
                    <textarea
                        v-model="catatan[pengajuan.id]"
                        rows="2"
                        placeholder="Alasan penolakan (wajib bila menolak)"
                        class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
                    />

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                            :disabled="memproses === pengajuan.id"
                            @click="putuskan(pengajuan, 'disetujui')"
                        >
                            Setujui
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                            :disabled="memproses === pengajuan.id"
                            @click="putuskan(pengajuan, 'ditolak')"
                        >
                            Tolak
                        </button>
                    </div>
                </div>

                <p v-else-if="pengajuan.catatan_admin" class="mt-3 rounded-md bg-stone-50 p-2 text-sm text-stone-700">
                    Catatan: {{ pengajuan.catatan_admin }}
                </p>
            </li>
        </ul>
    </div>
</template>
