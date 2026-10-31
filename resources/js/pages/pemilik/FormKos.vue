<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api, { csrf, galatKolom, pesanGalat } from '../../api';
import PemilihTitik from '../../components/PemilihTitik.vue';

const props = defineProps({ id: { type: [String, Number], default: null } });

const router = useRouter();
const mode = computed(() => (props.id ? 'ubah' : 'baru'));

const formulir = reactive({
    nama_kos: '',
    deskripsi: '',
    alamat: '',
    latitude: null,
    longitude: null,
    tipe_kos: 'campur',
    harga_per_bulan: '',
    jumlah_kamar: 1,
    sisa_kamar: 1,
    status: 'aktif',
    fasilitas: [],
});

const fasilitasPilihan = [
    'WiFi', 'Kamar Mandi Dalam', 'AC', 'Kasur', 'Lemari',
    'Meja Belajar', 'Dapur Bersama', 'Parkir Motor', 'Air Panas', 'Laundry',
];

const memuat = ref(false);
const mengirim = ref(false);
const galat = ref(null);
const galatPerKolom = ref({});

async function muat() {
    if (mode.value === 'baru') {
        return;
    }

    memuat.value = true;

    try {
        const { data } = await api.get(`/kos/${props.id}`);
        Object.assign(formulir, {
            nama_kos: data.data.nama_kos,
            deskripsi: data.data.deskripsi ?? '',
            alamat: data.data.alamat,
            latitude: data.data.latitude,
            longitude: data.data.longitude,
            tipe_kos: data.data.tipe_kos,
            harga_per_bulan: data.data.harga_per_bulan,
            jumlah_kamar: data.data.jumlah_kamar,
            sisa_kamar: data.data.sisa_kamar,
            status: data.data.status,
            fasilitas: data.data.fasilitas ?? [],
        });
    } catch (error) {
        galat.value = pesanGalat(error, 'Gagal memuat data kos.');
    } finally {
        memuat.value = false;
    }
}

async function simpan() {
    mengirim.value = true;
    galat.value = null;
    galatPerKolom.value = {};

    try {
        await csrf();

        if (mode.value === 'baru') {
            // sisa_kamar dan status sengaja tidak dikirim saat pembuatan.
            // Server menyamakan sisa_kamar dengan jumlah_kamar; stok kamar
            // dikendalikan alur pemesanan, bukan angka yang diketik di sini.
            const { nama_kos, deskripsi, alamat, latitude, longitude, tipe_kos, harga_per_bulan, jumlah_kamar, fasilitas } = formulir;

            await api.post('/kos', {
                nama_kos, deskripsi, alamat, latitude, longitude,
                tipe_kos, harga_per_bulan, jumlah_kamar, fasilitas,
            });
        } else {
            await api.put(`/kos/${props.id}`, { ...formulir });
        }

        router.push({ name: 'pemilik-kos' });
    } catch (error) {
        galat.value = pesanGalat(error, 'Data kos gagal disimpan.');
        galatPerKolom.value = galatKolom(error);
    } finally {
        mengirim.value = false;
    }
}

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 py-6">
        <RouterLink :to="{ name: 'pemilik-kos' }" class="text-sm text-stone-500 hover:underline">
            &larr; Kembali ke daftar kos
        </RouterLink>

        <h1 class="mt-3 text-2xl font-semibold tracking-tight">
            {{ mode === 'baru' ? 'Tambah Kos' : 'Ubah Data Kos' }}
        </h1>

        <p v-if="memuat" class="mt-6 text-sm text-stone-500">Memuat...</p>

        <form v-else class="mt-6 space-y-5" @submit.prevent="simpan">
            <p v-if="galat" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                {{ galat }}
            </p>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Nama kos</span>
                <input
                    v-model="formulir.nama_kos"
                    type="text"
                    required
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                >
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Alamat</span>
                <textarea
                    v-model="formulir.alamat"
                    rows="2"
                    required
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                />
            </label>

            <div>
                <span class="mb-1 block text-sm font-medium">Lokasi pada peta</span>
                <PemilihTitik
                    v-model:lat="formulir.latitude"
                    v-model:lng="formulir.longitude"
                />
                <p v-if="galatPerKolom.latitude" class="mt-1 text-xs text-red-700">
                    {{ galatPerKolom.latitude[0] }}
                </p>
            </div>

            <label class="block">
                <span class="mb-1 block text-sm font-medium">Deskripsi</span>
                <textarea
                    v-model="formulir.deskripsi"
                    rows="4"
                    class="w-full rounded-md border border-stone-300 px-3 py-2"
                    placeholder="Suasana kos, aturan, jam berkunjung, dan hal lain yang perlu diketahui penyewa."
                />
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Tipe kos</span>
                    <select
                        v-model="formulir.tipe_kos"
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                        <option value="putra">Khusus Putra</option>
                        <option value="putri">Khusus Putri</option>
                        <option value="campur">Campur</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Harga per bulan (Rp)</span>
                    <input
                        v-model.number="formulir.harga_per_bulan"
                        type="number"
                        min="0"
                        step="50000"
                        required
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium">Jumlah kamar</span>
                    <input
                        v-model.number="formulir.jumlah_kamar"
                        type="number"
                        min="1"
                        required
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                </label>

                <label v-if="mode === 'ubah'" class="block">
                    <span class="mb-1 block text-sm font-medium">Sisa kamar</span>
                    <input
                        v-model.number="formulir.sisa_kamar"
                        type="number"
                        min="0"
                        :max="formulir.jumlah_kamar"
                        required
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                    <span class="mt-1 block text-xs text-stone-500">
                        Angka ini berubah sendiri setiap ada pesanan diterima, dibatalkan,
                        atau selesai. Ubah hanya bila perlu menyesuaikan dengan keadaan
                        di luar sistem.
                    </span>
                </label>

                <label v-if="mode === 'ubah'" class="block">
                    <span class="mb-1 block text-sm font-medium">Status</span>
                    <select
                        v-model="formulir.status"
                        class="w-full rounded-md border border-stone-300 px-3 py-2"
                    >
                        <option value="aktif">Tayang</option>
                        <option value="nonaktif">Tidak tayang</option>
                    </select>
                </label>
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">Fasilitas</legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="fasilitas in fasilitasPilihan"
                        :key="fasilitas"
                        class="cursor-pointer rounded-full border px-3 py-1 text-sm"
                        :class="formulir.fasilitas.includes(fasilitas)
                            ? 'border-stone-900 bg-stone-900 text-white'
                            : 'border-stone-300 hover:bg-stone-100'"
                    >
                        <input
                            v-model="formulir.fasilitas"
                            type="checkbox"
                            :value="fasilitas"
                            class="sr-only"
                        >
                        {{ fasilitas }}
                    </label>
                </div>
            </fieldset>

            <div class="flex gap-3 border-t border-stone-200 pt-4">
                <button
                    type="submit"
                    class="rounded-md bg-stone-900 px-4 py-2 font-medium text-white hover:bg-stone-700 disabled:opacity-50"
                    :disabled="mengirim"
                >
                    {{ mengirim ? 'Menyimpan...' : 'Simpan' }}
                </button>

                <RouterLink
                    :to="{ name: 'pemilik-kos' }"
                    class="rounded-md border border-stone-300 px-4 py-2 hover:bg-stone-100"
                >
                    Batal
                </RouterLink>
            </div>
        </form>
    </div>
</template>
