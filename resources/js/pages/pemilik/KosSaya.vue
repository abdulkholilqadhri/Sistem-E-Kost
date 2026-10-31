<script setup>
import { onMounted, ref } from 'vue';
import api, { csrf, pesanGalat } from '../../api';

const daftar = ref([]);
const memuat = ref(true);
const galat = ref(null);
const mengunggah = ref(null);

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/kos-saya');
        daftar.value = data.data;
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat daftar kos.');
    } finally {
        memuat.value = false;
    }
}

async function unggahFoto(kos, peristiwa) {
    const berkas = Array.from(peristiwa.target.files ?? []);

    if (berkas.length === 0) {
        return;
    }

    mengunggah.value = kos.id;
    galat.value = null;

    try {
        await csrf();

        const muatan = new FormData();
        berkas.forEach((item) => muatan.append('foto[]', item));

        await api.post(`/kos/${kos.id}/foto`, muatan);
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Foto gagal diunggah.');
    } finally {
        mengunggah.value = null;
        peristiwa.target.value = '';
    }
}

async function hapusFoto(kos, foto) {
    if (!window.confirm('Hapus foto ini?')) {
        return;
    }

    try {
        await csrf();
        await api.delete(`/kos/${kos.id}/foto/${foto.id}`);
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Foto gagal dihapus.');
    }
}

async function hapusKos(kos) {
    if (!window.confirm(`Hapus ${kos.nama_kos}? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    try {
        await csrf();
        await api.delete(`/kos/${kos.id}`);
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Kos gagal dihapus.');
    }
}

function rupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka ?? 0);
}

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-6">
        <div v-muncul:naik class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Kos Saya</h1>
                <p class="mt-1 text-sm text-stone-600">Kelola data kos, foto, dan ketersediaan kamar.</p>
            </div>

            <RouterLink
                :to="{ name: 'pemilik-kos-baru' }"
                class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700"
            >
                Tambah Kos
            </RouterLink>
        </div>

        <p v-if="galat" class="mb-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            {{ galat }}
        </p>

        <p v-if="memuat" class="text-sm text-stone-500">Memuat...</p>

        <p
            v-else-if="daftar.length === 0"
            class="rounded-lg border border-stone-200 bg-white p-6 text-center text-sm text-stone-500"
        >
            Anda belum menambahkan kos.
        </p>

        <ul v-else class="space-y-4">
            <li
                v-for="(kos, urutan) in daftar"
                :key="kos.id"
                v-muncul="{ nama: 'naik', jeda: Math.min(urutan, 7) * 70 }"
                class="rounded-lg border border-stone-200 bg-white p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-medium">{{ kos.nama_kos }}</h2>
                        <p class="text-sm text-stone-500">{{ kos.alamat }}</p>
                        <p class="mt-1 text-sm">
                            {{ rupiah(kos.harga_per_bulan) }} / bulan &middot;
                            {{ kos.tipe_kos_label }} &middot;
                            <span :class="kos.sisa_kamar > 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ kos.sisa_kamar }} dari {{ kos.jumlah_kamar }} kamar kosong
                            </span>
                        </p>
                    </div>

                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="kos.status === 'aktif' ? 'bg-emerald-100 text-emerald-900' : 'bg-stone-200 text-stone-600'"
                    >
                        {{ kos.status === 'aktif' ? 'Tayang' : 'Tidak tayang' }}
                    </span>
                </div>

                <div v-if="kos.foto?.length" class="mt-3 flex flex-wrap gap-2">
                    <div v-for="foto in kos.foto" :key="foto.id" class="relative">
                        <img :src="foto.url" :alt="foto.keterangan ?? ''" class="size-20 rounded object-cover">
                        <button
                            type="button"
                            class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-red-600 text-xs text-white"
                            title="Hapus foto"
                            @click="hapusFoto(kos, foto)"
                        >
                            &times;
                        </button>
                    </div>
                </div>
                <p v-else class="mt-3 text-sm text-stone-500">Belum ada foto.</p>

                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-stone-100 pt-3 text-sm">
                    <RouterLink
                        :to="{ name: 'pemilik-kos-ubah', params: { id: kos.id } }"
                        class="rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-100"
                    >
                        Ubah data
                    </RouterLink>

                    <label class="cursor-pointer rounded-md border border-stone-300 px-3 py-1.5 hover:bg-stone-100">
                        {{ mengunggah === kos.id ? 'Mengunggah...' : 'Tambah foto' }}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="hidden"
                            :disabled="mengunggah === kos.id"
                            @change="unggahFoto(kos, $event)"
                        >
                    </label>

                    <RouterLink
                        :to="{ name: 'detail-kos', params: { id: kos.id } }"
                        class="text-stone-600 underline hover:text-stone-900"
                    >
                        Lihat sebagai penyewa
                    </RouterLink>

                    <button
                        type="button"
                        class="ml-auto text-red-700 underline hover:text-red-900"
                        @click="hapusKos(kos)"
                    >
                        Hapus
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
