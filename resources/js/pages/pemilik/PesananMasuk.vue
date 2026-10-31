<script setup>
import { onMounted, reactive, ref } from 'vue';
import api, { csrf, pesanGalat } from '../../api';

const daftar = ref([]);
const memuat = ref(true);
const galat = ref(null);
const memproses = ref(null);
const catatan = reactive({});

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/pemesanan-masuk');
        daftar.value = data.data;
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat pesanan masuk.');
    } finally {
        memuat.value = false;
    }
}

async function putuskan(pesanan, keputusan) {
    if (keputusan === 'tolak' && !catatan[pesanan.id]) {
        galat.value = 'Isi alasan penolakan terlebih dahulu.';

        return;
    }

    memproses.value = pesanan.id;
    galat.value = null;

    try {
        await csrf();
        await api.post(`/pemesanan/${pesanan.id}/keputusan`, {
            keputusan,
            catatan: catatan[pesanan.id] || undefined,
        });
        delete catatan[pesanan.id];
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Keputusan gagal disimpan.');
    } finally {
        memproses.value = null;
    }
}

async function verifikasiBayar(pesanan, keputusan) {
    if (keputusan === 'tolak' && !catatan[`bayar-${pesanan.id}`]) {
        galat.value = 'Isi alasan penolakan bukti pembayaran terlebih dahulu.';

        return;
    }

    memproses.value = pesanan.id;
    galat.value = null;

    try {
        await csrf();
        await api.post(`/pembayaran/${pesanan.pembayaran.id}/verifikasi`, {
            keputusan: keputusan === 'tolak' ? 'tolak' : 'setujui',
            catatan: catatan[`bayar-${pesanan.id}`] || undefined,
        });
        delete catatan[`bayar-${pesanan.id}`];
        await muat();
    } catch (error) {
        galat.value = pesanGalat(error, 'Verifikasi gagal disimpan.');
    } finally {
        memproses.value = null;
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
    <div class="mx-auto max-w-3xl px-4 py-6">
        <h1 v-muncul:naik class="text-2xl font-semibold tracking-tight">Pesanan Masuk</h1>
        <p v-muncul="{ nama: 'naik', jeda: 90 }" class="mt-1 text-sm text-stone-600">
            Terima atau tolak pesanan, lalu verifikasi bukti transfer yang dikirim penyewa.
        </p>

        <p v-if="galat" class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            {{ galat }}
        </p>

        <p v-if="memuat" class="mt-6 text-sm text-stone-500">Memuat...</p>

        <p
            v-else-if="daftar.length === 0"
            class="mt-6 rounded-lg border border-stone-200 bg-white p-6 text-center text-sm text-stone-500"
        >
            Belum ada pesanan masuk.
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
                        <h2 class="font-medium">{{ pesanan.kos?.nama_kos }}</h2>
                        <p class="text-sm text-stone-600">
                            Pemesan: {{ pesanan.penyewa?.nama }}
                            <span v-if="pesanan.penyewa?.no_hp" class="text-stone-400">
                                &middot; {{ pesanan.penyewa.no_hp }}
                            </span>
                        </p>
                        <p class="text-sm text-stone-500">
                            {{ tanggal(pesanan.tanggal_mulai) }} &ndash; {{ tanggal(pesanan.tanggal_selesai) }}
                            ({{ pesanan.durasi_bulan }} bulan)
                        </p>
                        <p class="mt-1 font-semibold">{{ rupiah(pesanan.total_harga) }}</p>
                    </div>

                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="warnaStatus[pesanan.status] ?? 'bg-stone-100 text-stone-700'"
                    >
                        {{ pesanan.status_label }}
                    </span>
                </div>

                <!-- Tahap 1: menerima atau menolak pesanan -->
                <div
                    v-if="pesanan.status === 'menunggu_konfirmasi'"
                    class="mt-4 space-y-2 rounded-md bg-stone-50 p-3"
                >
                    <p class="text-sm text-stone-600">
                        Menerima pesanan ini akan mengurangi satu kamar dari stok
                        {{ pesanan.kos?.nama_kos }}.
                    </p>

                    <textarea
                        v-model="catatan[pesanan.id]"
                        rows="2"
                        placeholder="Alasan penolakan (wajib bila menolak)"
                        class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
                    />

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                            :disabled="memproses === pesanan.id"
                            @click="putuskan(pesanan, 'terima')"
                        >
                            Terima
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                            :disabled="memproses === pesanan.id"
                            @click="putuskan(pesanan, 'tolak')"
                        >
                            Tolak
                        </button>
                    </div>
                </div>

                <p
                    v-else-if="pesanan.status === 'menunggu_pembayaran'"
                    class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-900"
                >
                    Menunggu penyewa mengunggah bukti transfer.
                </p>

                <!-- Tahap 2: memverifikasi bukti transfer -->
                <div
                    v-else-if="pesanan.status === 'menunggu_verifikasi' && pesanan.pembayaran"
                    class="mt-4 space-y-2 rounded-md bg-stone-50 p-3"
                >
                    <p class="text-sm font-medium">Bukti transfer diterima</p>
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-stone-500">Jumlah ditransfer</dt>
                            <dd class="font-medium">{{ rupiah(pesanan.pembayaran.jumlah_bayar) }}</dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Tagihan</dt>
                            <dd
                                class="font-medium"
                                :class="pesanan.pembayaran.jumlah_bayar !== pesanan.total_harga && 'text-red-700'"
                            >
                                {{ rupiah(pesanan.total_harga) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Nama pengirim</dt>
                            <dd>{{ pesanan.pembayaran.nama_pengirim ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-stone-500">Tanggal transfer</dt>
                            <dd>{{ tanggal(pesanan.pembayaran.tanggal_transfer) }}</dd>
                        </div>
                    </dl>

                    <p
                        v-if="pesanan.pembayaran.jumlah_bayar !== pesanan.total_harga"
                        class="rounded border border-amber-300 bg-amber-50 p-2 text-sm text-amber-900"
                    >
                        Nominal transfer tidak sama dengan tagihan. Periksa buktinya sebelum menyetujui.
                    </p>

                    <a
                        :href="`/api/pembayaran/${pesanan.pembayaran.id}/bukti`"
                        target="_blank"
                        rel="noopener"
                        class="inline-block text-sm underline hover:text-stone-900"
                    >
                        Buka berkas bukti transfer
                    </a>

                    <textarea
                        v-model="catatan[`bayar-${pesanan.id}`]"
                        rows="2"
                        placeholder="Alasan penolakan (wajib bila menolak)"
                        class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm"
                    />

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-md bg-stone-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                            :disabled="memproses === pesanan.id"
                            @click="verifikasiBayar(pesanan, 'setujui')"
                        >
                            Setujui pembayaran
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                            :disabled="memproses === pesanan.id"
                            @click="verifikasiBayar(pesanan, 'tolak')"
                        >
                            Tolak bukti
                        </button>
                    </div>
                </div>

                <p v-if="pesanan.catatan_pemilik" class="mt-3 rounded-md bg-stone-50 p-2 text-sm text-stone-700">
                    Catatan Anda: {{ pesanan.catatan_pemilik }}
                </p>
            </li>
        </ul>
    </div>
</template>
