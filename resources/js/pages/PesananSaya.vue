<script setup>
import { onMounted, reactive, ref } from 'vue';
import Button from 'primevue/button';
import Rating from 'primevue/rating';
import Textarea from 'primevue/textarea';
import api, { csrf, pesanGalat } from '../api';

const daftar = ref([]);
const memuat = ref(true);
const galat = ref(null);

/*
 * Pengunggahan bukti transfer tidak lagi dikerjakan di sini.
 *
 * Seluruh urusan pembayaran pindah ke halaman Pembayaran (SDD BAB IV, mockup
 * hal. 61), yang memuat rincian tagihan, hitung mundur tenggat, dan kontak
 * pemilik — hal-hal yang tidak muat di dalam kartu daftar ini. Halaman ini
 * cukup menautkannya.
 */
async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/pemesanan');
        daftar.value = data.data;
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat pesanan.');
    } finally {
        memuat.value = false;
    }
}

const formUlasan = reactive({});
const mengirimUlasan = ref(null);

function siapkanUlasan(id) {
    if (! formUlasan[id]) {
        formUlasan[id] = { rating: 0, komentar: '', galat: null };
    }

    return formUlasan[id];
}

/**
 * FR-09. Tombolnya hanya muncul bila server menyatakan pesanan ini boleh
 * diulas — yaitu masa sewanya sudah berakhir dan belum pernah diulas.
 * Pemeriksaan itu tetap diulang di server; yang di sini hanya agar pengguna
 * tidak disuguhi form yang pasti ditolak.
 */
async function kirimUlasan(pesanan) {
    const form = siapkanUlasan(pesanan.id);

    if (! form.rating) {
        form.galat = 'Beri penilaian bintang terlebih dahulu.';

        return;
    }

    mengirimUlasan.value = pesanan.id;
    form.galat = null;

    try {
        await csrf();
        await api.post(`/pemesanan/${pesanan.id}/ulasan`, {
            rating: form.rating,
            komentar: form.komentar || undefined,
        });

        delete formUlasan[pesanan.id];
        await muat();
    } catch (error) {
        form.galat = pesanGalat(error, 'Ulasan gagal dikirim.');
    } finally {
        mengirimUlasan.value = null;
    }
}

async function batalkan(pesanan) {
    if (!window.confirm('Batalkan pesanan ini?')) {
        return;
    }

    try {
        await csrf();
        await api.post(`/pemesanan/${pesanan.id}/batal`);
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Pesanan gagal dibatalkan.');
    }
}

function rupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka ?? 0);
}

function tanggal(iso) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', { dateStyle: 'medium' }) : '-';
}

function waktu(iso) {
    return iso ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
}

const warnaStatus = {
    menunggu_konfirmasi: 'bg-amber-100 text-amber-900',
    menunggu_pembayaran: 'bg-blue-100 text-blue-900',
    menunggu_verifikasi: 'bg-indigo-100 text-indigo-900',
    aktif: 'bg-emerald-100 text-emerald-900',
    selesai: 'bg-stone-200 text-stone-700',
    ditolak: 'bg-red-100 text-red-900',
    dibatalkan: 'bg-stone-200 text-stone-600',
};

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <h1 v-muncul:naik class="text-2xl font-semibold tracking-tight">Pesanan Saya</h1>

        <p v-if="memuat" class="mt-6 text-sm text-stone-500">Memuat...</p>

        <p v-else-if="galat" class="mt-6 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            {{ galat }}
        </p>

        <p v-else-if="daftar.length === 0" class="mt-6 rounded-lg border border-stone-200 bg-white p-6 text-center text-sm text-stone-500">
            Anda belum punya pesanan.
            <RouterLink :to="{ name: 'beranda' }" class="font-medium underline">Cari kos</RouterLink>
        </p>

        <ul v-else class="mt-6 space-y-4">
            <li
                v-for="(pesanan, urutan) in daftar"
                :key="pesanan.id"
                v-muncul="{ nama: 'naik', jeda: Math.min(urutan, 7) * 70 }"
                class="rounded-lg border border-stone-200 bg-white p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-medium">{{ pesanan.kos?.nama_kos ?? 'Kos' }}</h2>
                        <p class="text-sm text-stone-500">
                            {{ tanggal(pesanan.tanggal_mulai) }} &ndash; {{ tanggal(pesanan.tanggal_selesai) }}
                            ({{ pesanan.durasi_bulan }} bulan)
                        </p>
                    </div>

                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="warnaStatus[pesanan.status] ?? 'bg-stone-100 text-stone-700'"
                    >
                        {{ pesanan.status_label }}
                    </span>
                </div>

                <p class="mt-2 font-semibold">{{ rupiah(pesanan.total_harga) }}</p>

                <p v-if="pesanan.catatan_pemilik" class="mt-2 rounded-md bg-stone-50 p-2 text-sm text-stone-700">
                    Catatan pemilik: {{ pesanan.catatan_pemilik }}
                </p>

                <p
                    v-if="pesanan.status === 'menunggu_pembayaran' && pesanan.batas_pembayaran"
                    class="mt-2 text-sm text-blue-800"
                >
                    Batas unggah bukti: {{ waktu(pesanan.batas_pembayaran) }}.
                    Lewat dari itu pesanan dibatalkan otomatis.
                </p>

                <div
                    v-if="pesanan.pembayaran"
                    class="mt-3 rounded-md border border-stone-200 p-3 text-sm"
                >
                    <p class="font-medium">Pembayaran: {{ pesanan.pembayaran.status_label }}</p>
                    <p class="text-stone-600">{{ rupiah(pesanan.pembayaran.jumlah_bayar) }}</p>
                    <p v-if="pesanan.pembayaran.catatan" class="mt-1 text-red-800">
                        {{ pesanan.pembayaran.catatan }}
                    </p>
                </div>

                <!--
                    Pembayaran punya halamannya sendiri (SDD BAB IV, mockup
                    hal. 61), jadi di sini cukup tautannya. Formulir unggah
                    yang dulu ada di tempat ini dihapus: dua formulir untuk
                    satu pekerjaan berarti dua tempat yang harus diperbaiki
                    setiap kali aturan pembayaran berubah, dan cepat atau
                    lambat keduanya akan berbeda.
                -->
                <RouterLink
                    v-if="pesanan.status === 'menunggu_pembayaran'"
                    :to="{ name: 'pembayaran-detail', params: { id: pesanan.id } }"
                    class="mt-3 inline-flex items-center gap-2 rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700"
                >
                    Bayar &amp; unggah bukti
                    <i class="pi pi-arrow-right text-[0.7rem]" aria-hidden="true" />
                </RouterLink>

                <!-- FR-09 — menulis ulasan setelah masa sewa berakhir. -->
                <div
                    v-if="pesanan.boleh_diulas"
                    class="mt-3 space-y-3 rounded-md bg-stone-50 p-3"
                >
                    <div>
                        <p class="text-sm font-medium">Bagaimana pengalaman Anda di sini?</p>
                        <p class="mt-0.5 text-xs text-stone-500">
                            Ulasan Anda tampil di halaman kos ini dan tidak dapat diubah
                            setelah terkirim.
                        </p>
                    </div>

                    <p
                        v-if="siapkanUlasan(pesanan.id).galat"
                        class="rounded border border-red-300 bg-red-50 p-2 text-sm text-red-800"
                    >
                        {{ siapkanUlasan(pesanan.id).galat }}
                    </p>

                    <Rating v-model="siapkanUlasan(pesanan.id).rating" />

                    <Textarea
                        v-model="siapkanUlasan(pesanan.id).komentar"
                        rows="3"
                        class="w-full"
                        placeholder="Ceritakan kondisi kamar, fasilitas, dan pelayanan pemiliknya (opsional)"
                        maxlength="1000"
                    />

                    <Button
                        label="Kirim ulasan"
                        size="small"
                        :loading="mengirimUlasan === pesanan.id"
                        @click="kirimUlasan(pesanan)"
                    />
                </div>

                <p
                    v-else-if="pesanan.status === 'selesai'"
                    class="mt-3 text-sm text-stone-500"
                >
                    Anda sudah mengulas pesanan ini. Terima kasih.
                </p>

                <button
                    v-if="['menunggu_konfirmasi', 'menunggu_pembayaran'].includes(pesanan.status)"
                    type="button"
                    class="mt-3 text-sm text-red-700 underline hover:text-red-900"
                    @click="batalkan(pesanan)"
                >
                    Batalkan pesanan
                </button>
            </li>
        </ul>
    </div>
</template>
