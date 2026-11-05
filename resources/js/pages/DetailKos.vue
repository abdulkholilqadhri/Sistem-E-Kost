<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Divider from 'primevue/divider';
import Galleria from 'primevue/galleria';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import api, { csrf, pesanGalat } from '../api';
import { taburKonfeti } from '../konfeti';
import DaftarUlasan from '../components/DaftarUlasan.vue';
import PetaKos from '../components/PetaKos.vue';
import { useAuth } from '../stores/auth';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuth();
const router = useRouter();
const toast = useToast();
const konfigurasi = window.EKOST ?? {};

const kos = ref(null);
const memuat = ref(true);

const rute = ref(null);
const ruteTersedia = ref(null);
const memuatRute = ref(false);
const pesanRute = ref(null);

const formulirPesan = reactive({ tanggal_mulai: null, durasi_bulan: 1 });
const mengirimPesanan = ref(false);
const berhasilPesan = ref(false);

const besok = new Date();
besok.setDate(besok.getDate() + 1);

const acuan = computed(() =>
    konfigurasi.kampus?.lat == null
        ? null
        : {
            lat: konfigurasi.kampus.lat,
            lng: konfigurasi.kampus.lng,
            nama: konfigurasi.kampus.nama,
        },
);

const totalHarga = computed(
    () => (kos.value?.harga_per_bulan ?? 0) * (formulirPesan.durasi_bulan || 0),
);

const akhirSewa = computed(() => {
    if (!formulirPesan.tanggal_mulai) {
        return null;
    }

    const akhir = new Date(formulirPesan.tanggal_mulai);
    akhir.setMonth(akhir.getMonth() + (formulirPesan.durasi_bulan || 0));

    return akhir;
});

async function muatKos() {
    memuat.value = true;

    try {
        const { data } = await api.get(`/kos/${props.id}`);
        kos.value = data.data;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Kos tidak ditemukan',
            detail: pesanGalat(error, 'Data kos tidak dapat dimuat.'),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

/**
 * Rute dimuat terpisah dan kegagalannya tidak menghalangi apa pun di halaman
 * ini. OSRM yang dipakai server demo publik yang bisa mati kapan saja; halaman
 * detail tetap harus berguna tanpa garis rute.
 */
async function muatRute() {
    if (!acuan.value) {
        return;
    }

    memuatRute.value = true;

    try {
        const { data } = await api.get(`/kos/${props.id}/rute`);
        ruteTersedia.value = data.tersedia;
        rute.value = data.rute;
        pesanRute.value = data.pesan ?? null;
    } catch (error) {
        ruteTersedia.value = false;
        pesanRute.value = pesanGalat(error, 'Rute tidak dapat dimuat.');
    } finally {
        memuatRute.value = false;
    }
}

async function pesan() {
    if (!auth.masuk) {
        router.push({ name: 'masuk', query: { lanjut: `/kos/${props.id}` } });

        return;
    }

    mengirimPesanan.value = true;

    try {
        await csrf();
        await api.post('/pemesanan', {
            kos_id: kos.value.id,
            tanggal_mulai: formatTanggalIso(formulirPesan.tanggal_mulai),
            durasi_bulan: formulirPesan.durasi_bulan,
        });

        berhasilPesan.value = true;

        // Satu-satunya perayaan di seluruh aplikasi ini, dan hanya di sini:
        // mengajukan pesanan adalah satu-satunya tindakan yang benar-benar
        // berarti bagi penyewa.
        taburKonfeti();

        toast.add({
            severity: 'success',
            summary: 'Pesanan terkirim',
            detail: 'Pemilik kos akan mengonfirmasi lebih dulu.',
            life: 6000,
        });
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Pesanan gagal dikirim',
            detail: pesanGalat(error),
            life: 9000,
        });
    } finally {
        mengirimPesanan.value = false;
    }
}

/**
 * DatePicker memberi objek Date. Dikirim sebagai tanggal lokal, bukan
 * toISOString(), karena toISOString() mengubahnya ke UTC — dan untuk pengguna
 * di WITA itu menggeser tanggalnya mundur satu hari pada dini hari.
 */
function formatTanggalIso(tanggal) {
    if (!tanggal) {
        return null;
    }

    const tahun = tanggal.getFullYear();
    const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
    const hari = String(tanggal.getDate()).padStart(2, '0');

    return `${tahun}-${bulan}-${hari}`;
}

function gulirKeForm() {
    document.getElementById('form-pesan')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function rupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka ?? 0);
}

function formatJarak(meter) {
    if (meter == null) {
        return null;
    }

    return meter < 1000 ? `${meter} m` : `${(meter / 1000).toFixed(1)} km`;
}

function formatDurasi(detik) {
    const menit = Math.round((detik ?? 0) / 60);

    return menit < 60 ? `${menit} menit` : `${Math.floor(menit / 60)} jam ${menit % 60} menit`;
}

function tanggalPanjang(tanggal) {
    return tanggal?.toLocaleDateString('id-ID', { dateStyle: 'long' }) ?? '';
}

onMounted(async () => {
    await muatKos();

    if (kos.value) {
        muatRute();
    }
});
</script>

<template>
    <!-- pb-28 di layar kecil memberi ruang untuk batang pemesanan tetap di
         bawah, supaya ia tidak menutupi bagian akhir halaman. -->
    <!-- Lebih sempit daripada beranda, dan itu disengaja: halaman ini berisi
         teks yang dibaca panjang-panjang, dan baris sepanjang 1800 px
         melelahkan mata. Beranda berisi kartu dan peta, yang justru
         diuntungkan oleh lebar. -->
    <div class="mx-auto max-w-6xl px-4 py-6 pb-28 sm:px-5 lg:px-6 lg:pb-6">
        <div v-if="memuat" class="space-y-4">
            <Skeleton width="40%" height="2rem" />
            <Skeleton height="20rem" />
            <Skeleton width="100%" height="8rem" />
        </div>

        <template v-else-if="kos">
            <RouterLink
                :to="{ name: 'beranda' }"
                class="inline-flex items-center gap-1.5 text-sm text-[color:var(--color-tinta)]/60 hover:text-[color:var(--color-indigo)]"
            >
                <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                Kembali ke pencarian
            </RouterLink>

            <header v-muncul:naik class="mt-3 mb-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 class="font-display text-3xl leading-tight font-extrabold tracking-tight">
                            {{ kos.nama_kos }}
                        </h1>
                        <p class="mt-1 text-[color:var(--color-tinta)]/70">{{ kos.alamat }}</p>
                    </div>

                    <div class="flex gap-2">
                        <Tag :value="kos.tipe_kos_label" severity="secondary" />
                        <Tag
                            :value="kos.tersedia ? `${kos.sisa_kamar} kamar kosong` : 'Penuh'"
                            :severity="kos.tersedia ? 'success' : 'danger'"
                        />
                    </div>
                </div>
            </header>

            <!-- Foto mengarah dari buram, sama seperti peta di beranda:
                 gambar memang datang sebagai bidang warna lalu menjadi
                 jelas, sehingga gerakannya meniru apa yang sebenarnya
                 terjadi saat berkasnya diunduh. -->
            <Galleria
                v-if="kos.foto?.length"
                v-muncul="{ nama: 'kabur', jeda: 120 }"
                :value="kos.foto"
                :num-visible="5"
                :responsive-options="[
                    { breakpoint: '1024px', numVisible: 4 },
                    { breakpoint: '640px', numVisible: 3 },
                ]"
                :circular="kos.foto.length > 1"
                :show-thumbnails="kos.foto.length > 1"
                :show-item-navigators="kos.foto.length > 1"
                container-class="mb-6 overflow-hidden rounded-lg"
            >
                <template #item="{ item }">
                    <img
                        :src="item.url"
                        :alt="item.keterangan ?? kos.nama_kos"
                        class="block max-h-[26rem] w-full object-cover"
                    >
                </template>
                <template #thumbnail="{ item }">
                    <img
                        :src="item.url"
                        :alt="item.keterangan ?? ''"
                        class="block size-16 object-cover"
                    >
                </template>
            </Galleria>

            <div
                v-else
                class="mb-6 flex h-48 items-center justify-center rounded-lg border border-dashed border-[color:var(--color-garis)] bg-white text-sm text-[color:var(--color-tinta)]/50"
            >
                Pemilik belum mengunggah foto
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    <!-- Kolom kiri masuk dari kiri, kartu pemesanan di kanan
                         masuk dari kanan. Arah geraknya mengikuti tempat
                         masing-masing, sehingga halaman terasa menyusun
                         dirinya alih-alih bergetar di satu arah. -->
                    <section v-muncul="{ nama: 'kiri', jeda: 200 }">
                        <h2 class="font-display text-xl font-semibold">Tentang kos ini</h2>
                        <p class="mt-2 max-w-prose leading-relaxed whitespace-pre-line text-[color:var(--color-tinta)]/85">
                            {{ kos.deskripsi || 'Pemilik belum menuliskan deskripsi.' }}
                        </p>
                    </section>

                    <template v-if="kos.fasilitas?.length">
                        <Divider />
                        <section>
                            <h2 v-muncul:kiri class="font-display text-xl font-semibold">Fasilitas</h2>
                            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                <li
                                    v-for="(fasilitas, urutan) in kos.fasilitas"
                                    :key="fasilitas"
                                    v-muncul="{ nama: 'kiri', jeda: Math.min(urutan, 7) * 55 }"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <i class="pi pi-check text-xs text-[color:var(--color-indigo)]" aria-hidden="true" />
                                    {{ fasilitas }}
                                </li>
                            </ul>
                        </section>
                    </template>

                    <Divider />

                    <section>
                        <h2 class="font-display text-xl font-semibold">Seberapa jauh dari kampus</h2>

                        <!--
                            Dua angka jarak yang artinya berbeda ditampilkan
                            berdampingan dengan label masing-masing. Garis lurus
                            berasal dari Haversine, jarak tempuh dari OSRM dan
                            selalu lebih besar. Tanpa penjelasan, selisihnya
                            terlihat seperti kesalahan sistem.
                        -->
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div
                                v-muncul:mekar
                                class="rounded-lg border border-[color:var(--color-garis)] bg-white p-3"
                            >
                                <dt class="text-sm text-[color:var(--color-tinta)]/60">Garis lurus</dt>
                                <dd class="font-display mt-0.5 text-2xl font-semibold text-[color:var(--color-emas)]">
                                    {{ formatJarak(kos.jarak_meter) ?? '—' }}
                                </dd>
                                <dd class="mt-0.5 text-xs text-[color:var(--color-tinta)]/50">
                                    jarak lurus di peta
                                </dd>
                            </div>

                            <div
                                v-muncul="{ nama: 'mekar', jeda: 110 }"
                                class="rounded-lg border border-[color:var(--color-garis)] bg-white p-3"
                            >
                                <dt class="text-sm text-[color:var(--color-tinta)]/60">Lewat jalan</dt>
                                <dd class="font-display mt-0.5 text-2xl font-semibold">
                                    <Skeleton v-if="memuatRute" width="5rem" height="1.8rem" />
                                    <template v-else-if="rute">{{ formatJarak(rute.jarak_tempuh_meter) }}</template>
                                    <template v-else>—</template>
                                </dd>
                                <dd v-if="rute" class="mt-0.5 text-xs text-[color:var(--color-tinta)]/50">
                                    sekitar {{ formatDurasi(rute.durasi_detik) }} berkendara
                                </dd>
                            </div>
                        </dl>

                        <Message
                            v-if="ruteTersedia === false"
                            severity="warn"
                            :closable="false"
                            class="mt-3"
                        >
                            {{ pesanRute }}
                        </Message>

                        <div
                            v-muncul="{ nama: 'kabur', jeda: 180 }"
                            class="mt-3 h-80 overflow-hidden rounded-lg border border-[color:var(--color-garis)]"
                        >
                            <PetaKos :daftar-kos="[kos]" :acuan="acuan" :radius="0" :rute="rute" />
                        </div>
                    </section>

                    <Divider />

                    <DaftarUlasan
                        v-muncul:naik
                        :kos-id="kos.id"
                        :rating-rata="kos.rating_rata"
                        :jumlah-ulasan="kos.jumlah_ulasan ?? 0"
                    />
                </div>

                <aside id="form-pesan" class="scroll-mt-20 lg:sticky lg:top-20 lg:self-start">
                    <div
                        v-muncul="{ nama: 'kanan', jeda: 260 }"
                        class="rounded-xl border border-[color:var(--color-garis)] bg-white p-4"
                    >
                        <p class="font-display text-3xl font-extrabold">
                            {{ rupiah(kos.harga_per_bulan) }}
                            <span class="text-base font-normal text-[color:var(--color-tinta)]/50">per bulan</span>
                        </p>

                        <Divider />

                        <div v-if="berhasilPesan">
                            <Message severity="success" :closable="false">
                                Pesanan terkirim. Pemilik kos akan mengonfirmasi lebih dulu,
                                baru Anda diminta mengunggah bukti pembayaran.
                            </Message>
                            <Button
                                label="Lihat pesanan saya"
                                class="mt-3 w-full"
                                @click="router.push({ name: 'pesanan-saya' })"
                            />
                        </div>

                        <form v-else-if="kos.tersedia" class="space-y-4" @submit.prevent="pesan">
                            <div>
                                <label for="mulai" class="mb-1.5 block text-sm font-medium">Mulai sewa</label>
                                <DatePicker
                                    id="mulai"
                                    v-model="formulirPesan.tanggal_mulai"
                                    :min-date="besok"
                                    date-format="dd MM yy"
                                    show-icon
                                    icon-display="input"
                                    class="w-full"
                                    required
                                />
                            </div>

                            <div>
                                <label for="durasi" class="mb-1.5 block text-sm font-medium">Durasi</label>
                                <InputNumber
                                    id="durasi"
                                    v-model="formulirPesan.durasi_bulan"
                                    :min="1"
                                    :max="12"
                                    show-buttons
                                    suffix=" bulan"
                                    class="w-full"
                                    input-class="w-full"
                                />
                            </div>

                            <p v-if="akhirSewa" class="text-sm text-[color:var(--color-tinta)]/70">
                                Sewa berakhir {{ tanggalPanjang(akhirSewa) }}
                            </p>

                            <div class="flex items-baseline justify-between border-t border-[color:var(--color-garis)] pt-3">
                                <span class="text-sm text-[color:var(--color-tinta)]/70">
                                    {{ formulirPesan.durasi_bulan }} bulan
                                </span>
                                <span class="font-display text-xl font-semibold">{{ rupiah(totalHarga) }}</span>
                            </div>

                            <!-- Satu-satunya tombol bermagnet di seluruh
                                 aplikasi. Gerakan ini bekerja dengan menarik
                                 perhatian ke satu tindakan; kalau semua tombol
                                 bermagnet, tidak ada lagi yang menonjol. -->
                            <Button
                                v-magnet
                                type="submit"
                                label="Ajukan pesanan"
                                class="w-full"
                                :loading="mengirimPesanan"
                                :disabled="!formulirPesan.tanggal_mulai"
                            />

                            <p class="text-xs leading-relaxed text-[color:var(--color-tinta)]/60">
                                Pembayaran ditransfer langsung ke pemilik kos setelah pesanan
                                dikonfirmasi. Sistem hanya mencatat bukti transfernya.
                            </p>
                        </form>

                        <div v-else>
                            <Message severity="warn" :closable="false">
                                Seluruh kamar di kos ini sedang terisi.
                            </Message>
                            <Button
                                label="Cari kos lain di sekitar sini"
                                severity="secondary"
                                outlined
                                class="mt-3 w-full"
                                @click="router.push({ name: 'beranda' })"
                            />
                        </div>
                    </div>
                </aside>
            </div>

            <!--
                Batang pemesanan tetap di bawah layar ponsel.
                Di layar kecil, kartu pemesanan berada jauh di bawah deskripsi,
                foto, dan peta — pengguna harus menggulir panjang hanya untuk
                tahu harganya. Batang ini menjaga harga dan tombolnya selalu
                terjangkau. Di layar lebar kartunya sudah menempel di sisi
                kanan, jadi batang ini disembunyikan.
            -->
            <div
                v-if="kos.tersedia && !berhasilPesan"
                class="fixed inset-x-0 bottom-0 z-20 flex items-center justify-between gap-4 border-t border-[color:var(--color-garis)] bg-white px-4 py-3 shadow-[0_-6px_20px_-12px_rgba(22,35,63,0.5)] lg:hidden"
            >
                <div>
                    <p class="font-display text-lg leading-none font-extrabold">
                        {{ rupiah(kos.harga_per_bulan) }}
                    </p>
                    <p class="mt-0.5 text-xs text-[color:var(--color-tinta)]/60">per bulan</p>
                </div>

                <Button label="Pesan kamar" @click="gulirKeForm" />
            </div>
        </template>
    </div>
</template>
