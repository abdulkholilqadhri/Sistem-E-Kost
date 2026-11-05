<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import { useToast } from 'primevue/usetoast';
import api, { csrf, galatKolom, pesanGalat } from '../api';

const props = defineProps({ id: { type: [String, Number], required: true } });

const router = useRouter();
const toast = useToast();

const pemesanan = ref(null);
const memuat = ref(true);

const formulir = reactive({
    nama_pengirim: '',
    tanggal_transfer: new Date(),
    jumlah_bayar: null,
    bukti: null,
});

const mengirim = ref(false);
const galatKolomBukti = ref({});

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get(`/pemesanan/${props.id}`);
        pemesanan.value = data.data;
        formulir.jumlah_bayar = data.data.total_harga;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Pesanan tidak dapat dibuka',
            detail: pesanGalat(error),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

/*
 * Empat langkah dari mockup SDD hal. 61. Langkah mana yang sedang berjalan
 * diturunkan dari status pesanan, bukan disimpan sendiri — status di server
 * adalah satu-satunya kebenaran, dan menyalinnya ke sini hanya menciptakan
 * dua sumber yang bisa berselisih.
 */
const langkah = computed(() => {
    const status = pemesanan.value?.status;

    const aktif = {
        menunggu_konfirmasi: 1,
        menunggu_pembayaran: 2,
        menunggu_verifikasi: 3,
        aktif: 3,
        selesai: 3,
    }[status] ?? 0;

    return [
        { label: 'Pilih Kamar', keterangan: pemesanan.value?.kos?.nama_kos ?? '' },
        { label: 'Formulir Pemesanan', keterangan: 'Tanggal & durasi' },
        { label: 'Pembayaran', keterangan: 'Transfer & bukti' },
        { label: 'Kamar Siap', keterangan: 'Sewa aktif' },
    ].map((satu, urutan) => ({
        ...satu,
        nomor: urutan + 1,
        selesai: urutan < aktif,
        sedang: urutan === aktif,
    }));
});

const bolehUnggah = computed(() => pemesanan.value?.status === 'menunggu_pembayaran');

/*
 * Hitung mundur batas pembayaran.
 *
 * Ini tampilan saja. Pembatalan pesanan yang lewat tenggat dikerjakan perintah
 * terjadwal pemesanan:kedaluwarsa di server — kalau hitung mundur di sini yang
 * memutuskan, pesanan tidak akan pernah batal pada penyewa yang menutup
 * perambannya.
 */
const sekarang = ref(Date.now());
let detak = null;

const sisaWaktu = computed(() => {
    const batas = pemesanan.value?.batas_pembayaran;

    if (!batas) {
        return null;
    }

    const selisih = new Date(batas).getTime() - sekarang.value;

    if (selisih <= 0) {
        return { habis: true, teks: '00:00:00' };
    }

    const jam = Math.floor(selisih / 3_600_000);
    const menit = Math.floor((selisih % 3_600_000) / 60_000);
    const detik = Math.floor((selisih % 60_000) / 1000);

    const dua = (angka) => String(angka).padStart(2, '0');

    return { habis: false, teks: `${dua(jam)}:${dua(menit)}:${dua(detik)}` };
});

async function kirim() {
    mengirim.value = true;
    galatKolomBukti.value = {};

    try {
        await csrf();

        const muatan = new FormData();
        muatan.append('bukti', formulir.bukti);
        muatan.append('jumlah_bayar', formulir.jumlah_bayar);

        if (formulir.nama_pengirim) {
            muatan.append('nama_pengirim', formulir.nama_pengirim);
        }

        if (formulir.tanggal_transfer) {
            muatan.append('tanggal_transfer', tanggalIso(formulir.tanggal_transfer));
        }

        await api.post(`/pemesanan/${props.id}/pembayaran`, muatan);

        toast.add({
            severity: 'success',
            summary: 'Bukti terkirim',
            detail: 'Pemilik kos akan memverifikasinya.',
            life: 6000,
        });

        await muat();
    } catch (error) {
        galatKolomBukti.value = galatKolom(error);
        toast.add({
            severity: 'error',
            summary: 'Bukti gagal dikirim',
            detail: pesanGalat(error),
            life: 9000,
        });
    } finally {
        mengirim.value = false;
    }
}

/**
 * Dikirim sebagai tanggal lokal, bukan toISOString(): toISOString() mengubah
 * ke UTC, dan bagi pengguna di WITA itu menggeser tanggalnya mundur satu hari
 * pada dini hari — lalu ditolak server sebagai "tanggal di masa depan".
 */
function tanggalIso(tanggal) {
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
    const hari = String(tanggal.getDate()).padStart(2, '0');

    return `${tanggal.getFullYear()}-${bulan}-${hari}`;
}

function rupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka ?? 0);
}

function tanggalPanjang(iso) {
    return iso ? new Date(iso).toLocaleDateString('id-ID', { dateStyle: 'long' }) : '-';
}

onMounted(() => {
    muat();
    detak = setInterval(() => { sekarang.value = Date.now(); }, 1000);
});

onBeforeUnmount(() => clearInterval(detak));
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div v-if="memuat" class="space-y-4">
            <Skeleton height="3rem" />
            <Skeleton height="5rem" />
            <Skeleton height="20rem" />
        </div>

        <template v-else-if="pemesanan">
            <!--
                Pita status dan hitung mundur, mengikuti mockup hal. 61.
                Keduanya di paling atas karena inilah yang paling mendesak:
                kamar dilepas kembali bila tenggatnya lewat.
            -->
            <div
                v-if="bolehUnggah"
                v-muncul:naik
                class="mb-5 rounded-xl border border-[color:var(--color-emas)] bg-[color:var(--color-emas-muda)]/55 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="pi pi-exclamation-circle text-[color:var(--color-emas)]" aria-hidden="true" />
                        <span>
                            <strong>Menunggu pembayaran.</strong>
                            Selesaikan transfer sebelum tenggatnya habis agar kamar tidak dilepas kembali.
                        </span>
                    </div>

                    <div v-if="sisaWaktu" class="text-right">
                        <p class="text-xs text-[color:var(--color-tinta)]/60">Sisa waktu</p>
                        <p
                            class="font-display text-2xl leading-none font-extrabold tabular-nums"
                            :class="sisaWaktu.habis && 'text-[color:var(--color-maroon)]'"
                        >
                            {{ sisaWaktu.teks }}
                        </p>
                    </div>
                </div>
            </div>

            <header v-muncul="{ nama: 'naik', jeda: 70 }" class="mb-5">
                <h1 class="font-display text-3xl font-extrabold tracking-tight">
                    Pembayaran &amp; Konfirmasi Sewa
                </h1>
                <p class="mt-1 text-sm text-[color:var(--color-tinta)]/65">
                    Transfer langsung ke pemilik kos, lalu unggah buktinya di sini.
                </p>
            </header>

            <!-- Stepper empat langkah. -->
            <ol
                v-muncul="{ nama: 'naik', jeda: 130 }"
                class="mb-6 grid gap-3 rounded-xl border border-[color:var(--color-garis)] bg-white p-4 sm:grid-cols-4"
            >
                <li
                    v-for="satu in langkah"
                    :key="satu.nomor"
                    class="flex items-center gap-3 rounded-lg px-3 py-2"
                    :class="satu.sedang && 'bg-[color:var(--color-kertas)]'"
                    :aria-current="satu.sedang ? 'step' : undefined"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        :class="satu.selesai
                            ? 'bg-emerald-600 text-white'
                            : satu.sedang
                                ? 'bg-[color:var(--color-indigo)] text-white'
                                : 'bg-[color:var(--color-kertas)] text-[color:var(--color-tinta)]/45'"
                        aria-hidden="true"
                    >
                        <i v-if="satu.selesai" class="pi pi-check text-[0.6rem]" />
                        <template v-else>{{ satu.nomor }}</template>
                    </span>

                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{ satu.label }}</span>
                        <span class="block truncate text-xs text-[color:var(--color-tinta)]/55">
                            {{ satu.keterangan }}
                        </span>
                    </span>
                </li>
            </ol>

            <div class="grid gap-5 lg:grid-cols-2">
                <div class="space-y-5">
                    <!-- Rincian tagihan. -->
                    <section
                        v-muncul="{ nama: 'kiri', jeda: 190 }"
                        class="overflow-hidden rounded-xl border border-[color:var(--color-garis)] bg-white"
                    >
                        <h2 class="border-b border-[color:var(--color-garis)] px-4 py-3 font-display font-semibold">
                            Rincian tagihan
                        </h2>

                        <div class="flex gap-3 border-b border-[color:var(--color-garis)] p-4">
                            <img
                                v-if="pemesanan.kos?.foto?.[0]?.url"
                                :src="pemesanan.kos.foto[0].url"
                                :alt="pemesanan.kos.nama_kos"
                                class="size-20 shrink-0 rounded-lg object-cover"
                            >
                            <div class="min-w-0">
                                <p class="font-semibold">{{ pemesanan.kos?.nama_kos }}</p>
                                <p class="mt-0.5 text-sm text-[color:var(--color-tinta)]/65">
                                    {{ pemesanan.kos?.alamat }}
                                </p>
                                <span
                                    class="mt-1.5 inline-block rounded bg-[color:var(--color-kertas)] px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ pemesanan.kos?.tipe_kos_label }}
                                </span>
                            </div>
                        </div>

                        <dl class="space-y-2 p-4 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-[color:var(--color-tinta)]/65">Periode hunian</dt>
                                <dd class="text-right">
                                    {{ tanggalPanjang(pemesanan.tanggal_mulai) }} &ndash;
                                    {{ tanggalPanjang(pemesanan.tanggal_selesai) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-[color:var(--color-tinta)]/65">Sewa per bulan</dt>
                                <dd>{{ rupiah(pemesanan.harga_per_bulan) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-[color:var(--color-tinta)]/65">Durasi</dt>
                                <dd>{{ pemesanan.durasi_bulan }} bulan</dd>
                            </div>

                            <div class="flex items-baseline justify-between gap-4 border-t border-[color:var(--color-garis)] pt-3">
                                <dt class="font-semibold">Total yang ditransfer</dt>
                                <dd class="font-display text-2xl font-extrabold">
                                    {{ rupiah(pemesanan.total_harga) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <!--
                        Tujuan transfer.

                        Mockup SDD menggambarkan rekening escrow atas nama
                        pengelola, tetapi SRS menyatakan pembayaran ditransfer
                        langsung ke pemilik kos dan sistem hanya mencatat
                        buktinya. Dua model yang berbeda; yang diikuti di sini
                        adalah SRS, karena itu yang menentukan perilaku sistem.
                    -->
                    <section
                        v-muncul="{ nama: 'kiri', jeda: 250 }"
                        class="rounded-xl border border-[color:var(--color-garis)] bg-white p-4"
                    >
                        <h2 class="font-display font-semibold">Transfer ke pemilik kos</h2>

                        <p class="mt-1 text-sm text-[color:var(--color-tinta)]/70">
                            E-Kost tidak menerima atau menyimpan uang Anda. Pembayaran dilakukan
                            langsung ke pemilik kos, dan sistem ini hanya mencatat bukti transfernya.
                        </p>

                        <div
                            v-if="pemesanan.kontak_pemilik"
                            class="mt-3 rounded-lg bg-[color:var(--color-kertas)] p-3 text-sm"
                        >
                            <p class="font-semibold">{{ pemesanan.kontak_pemilik.nama }}</p>
                            <p v-if="pemesanan.kontak_pemilik.no_hp" class="mt-0.5">
                                {{ pemesanan.kontak_pemilik.no_hp }}
                            </p>
                            <p class="mt-2 text-xs text-[color:var(--color-tinta)]/65">
                                Hubungi untuk menanyakan nomor rekening tujuan.
                            </p>
                        </div>
                    </section>
                </div>

                <!-- Unggah bukti. -->
                <section
                    v-muncul="{ nama: 'kanan', jeda: 220 }"
                    class="h-fit rounded-xl border border-[color:var(--color-garis)] bg-white p-4"
                >
                    <h2 class="font-display font-semibold">Unggah bukti transfer</h2>

                    <template v-if="bolehUnggah">
                        <p class="mt-1 text-sm text-[color:var(--color-tinta)]/70">
                            Lengkapi datanya agar pemilik kos dapat mencocokkan transfer Anda.
                        </p>

                        <form class="mt-4 space-y-4" @submit.prevent="kirim">
                            <div>
                                <label for="pengirim" class="mb-1.5 block text-sm font-medium">
                                    Nama pemilik rekening pengirim
                                </label>
                                <InputText
                                    id="pengirim"
                                    v-model="formulir.nama_pengirim"
                                    class="w-full"
                                    placeholder="Contoh: Ahmad Fauzi"
                                />
                                <p v-if="galatKolomBukti.nama_pengirim" class="mt-1 text-sm text-[color:var(--color-maroon)]">
                                    {{ galatKolomBukti.nama_pengirim }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="tanggal" class="mb-1.5 block text-sm font-medium">
                                        Tanggal transfer
                                    </label>
                                    <DatePicker
                                        id="tanggal"
                                        v-model="formulir.tanggal_transfer"
                                        :max-date="new Date()"
                                        date-format="dd MM yy"
                                        show-icon
                                        icon-display="input"
                                        class="w-full"
                                    />
                                    <p v-if="galatKolomBukti.tanggal_transfer" class="mt-1 text-sm text-[color:var(--color-maroon)]">
                                        {{ galatKolomBukti.tanggal_transfer }}
                                    </p>
                                </div>

                                <div>
                                    <label for="jumlah" class="mb-1.5 block text-sm font-medium">
                                        Jumlah transfer
                                    </label>
                                    <InputNumber
                                        id="jumlah"
                                        v-model="formulir.jumlah_bayar"
                                        mode="currency"
                                        currency="IDR"
                                        locale="id-ID"
                                        :max-fraction-digits="0"
                                        class="w-full"
                                        input-class="w-full"
                                    />
                                    <p v-if="galatKolomBukti.jumlah_bayar" class="mt-1 text-sm text-[color:var(--color-maroon)]">
                                        {{ galatKolomBukti.jumlah_bayar }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label for="bukti" class="mb-1.5 block text-sm font-medium">
                                    Struk atau tangkapan layar
                                </label>
                                <input
                                    id="bukti"
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    required
                                    class="w-full rounded-lg border border-dashed border-[color:var(--color-garis)] p-3 text-sm"
                                    @change="formulir.bukti = $event.target.files?.[0] ?? null"
                                >
                                <p class="mt-1 text-xs text-[color:var(--color-tinta)]/60">
                                    JPG, PNG, atau PDF. Pastikan nominal dan tanggalnya terbaca.
                                </p>
                                <p v-if="galatKolomBukti.bukti" class="mt-1 text-sm text-[color:var(--color-maroon)]">
                                    {{ galatKolomBukti.bukti }}
                                </p>
                            </div>

                            <Button
                                type="submit"
                                label="Kirim bukti transfer"
                                class="w-full"
                                :loading="mengirim"
                                :disabled="!formulir.bukti"
                            />
                        </form>
                    </template>

                    <!-- Status selain menunggu pembayaran. -->
                    <div v-else class="mt-3">
                        <Message
                            v-if="pemesanan.status === 'menunggu_verifikasi'"
                            severity="info"
                            :closable="false"
                        >
                            Bukti sudah terkirim. Pemilik kos sedang memeriksanya.
                        </Message>

                        <Message
                            v-else-if="pemesanan.status === 'aktif'"
                            severity="success"
                            :closable="false"
                        >
                            Pembayaran terverifikasi. Sewa Anda sudah aktif.
                        </Message>

                        <Message
                            v-else-if="pemesanan.status === 'menunggu_konfirmasi'"
                            severity="warn"
                            :closable="false"
                        >
                            Pemilik kos belum mengonfirmasi pesanan ini. Pembayaran dibuka
                            setelah pesanannya diterima.
                        </Message>

                        <Message v-else severity="secondary" :closable="false">
                            Pesanan ini berstatus {{ pemesanan.status_label }}, sehingga tidak ada
                            pembayaran yang perlu dikirim.
                        </Message>

                        <Button
                            label="Kembali ke pesanan saya"
                            severity="secondary"
                            outlined
                            class="mt-4 w-full"
                            @click="router.push({ name: 'pesanan-saya' })"
                        />
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
