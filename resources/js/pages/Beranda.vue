<script setup>
import { nextTick, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import Slider from 'primevue/slider';
import { useToast } from 'primevue/usetoast';
import api, { pesanGalat } from '../api';
import PetaKos from '../components/PetaKos.vue';
import KartuKos from '../components/KartuKos.vue';

const toast = useToast();
const konfigurasi = window.EKOST ?? {};

const acuan = ref(
    konfigurasi.kampus?.lat != null && konfigurasi.kampus?.lng != null
        ? {
            lat: konfigurasi.kampus.lat,
            lng: konfigurasi.kampus.lng,
            nama: konfigurasi.kampus.nama,
        }
        : null,
);

const filter = reactive({
    radius: konfigurasi.radius_default ?? 1000,
    tipe_kos: null,
    harga_maks: null,
    q: '',
});

const pilihanTipe = [
    { label: 'Semua', value: null },
    { label: 'Putra', value: 'putra' },
    { label: 'Putri', value: 'putri' },
    { label: 'Campur', value: 'campur' },
];

// Pilihan radius yang paling sering dipakai, supaya tidak perlu menggeser
// slider untuk nilai-nilai yang umum.
const pilihanRadius = [500, 1000, 2000, 5000];

const daftarKos = ref([]);
const memuat = ref(true);
const kosTersorot = ref(null);
const menungguLokasi = ref(false);

/**
 * Hanya berlaku di layar kecil. Daftar dan peta tidak muat berdampingan di
 * ponsel, dan menumpuknya membuat peta mendorong seluruh daftar ke bawah
 * layar. Di layar lebar keduanya tetap tampil bersama dan nilai ini diabaikan.
 */
const tampilan = ref('daftar');

async function cari() {
    if (!acuan.value) {
        toast.add({
            severity: 'warn',
            summary: 'Titik acuan belum ada',
            detail: 'Klik pada peta untuk menentukan dari mana jarak dihitung.',
            life: 6000,
        });

        return;
    }

    memuat.value = true;

    try {
        const { data } = await api.get('/kos/cari', {
            params: {
                lat: acuan.value.lat,
                lng: acuan.value.lng,
                radius: filter.radius,
                tipe_kos: filter.tipe_kos ?? undefined,
                harga_maks: filter.harga_maks ?? undefined,
                q: filter.q || undefined,
                per_halaman: 24,
            },
        });

        daftarKos.value = data.data;
    } catch (error) {
        daftarKos.value = [];
        toast.add({
            severity: 'error',
            summary: 'Pencarian gagal',
            detail: pesanGalat(error, 'Daftar kos tidak dapat dimuat.'),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

function pilihTipe(nilai) {
    filter.tipe_kos = nilai;
    cari();
}

function pilihRadius(nilai) {
    filter.radius = nilai;
}

function geserAcuan(posisi) {
    acuan.value = { ...posisi, nama: 'Titik pilihan Anda' };
    cari();
}

/**
 * Penanda di peta diklik: kartunya ikut disorot dan digulirkan ke tampilan,
 * sehingga hubungan antara titik di peta dan kartu di daftar terasa langsung.
 *
 * Penggulirannya dilewati bila kartunya sedang tidak tampil — misalnya saat
 * ponsel menampilkan peta saja. offsetParent bernilai null untuk elemen yang
 * tersembunyi, dan itu pemeriksaan yang cukup di sini.
 */
function pilihDariPeta(id) {
    kosTersorot.value = id;

    nextTick(() => {
        const kartu = document.getElementById(`kos-${id}`);

        if (kartu?.offsetParent) {
            kartu.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

/**
 * Geolocation API diblokir peramban pada koneksi non-HTTPS, kecuali localhost.
 * Begitu aplikasi ditempatkan di server tanpa SSL, tombol ini mati total —
 * karena itu kegagalannya dijelaskan apa adanya, bukan didiamkan.
 */
function pakaiLokasiSaya() {
    if (!navigator.geolocation) {
        toast.add({
            severity: 'warn',
            summary: 'Lokasi tidak didukung',
            detail: 'Peramban ini tidak menyediakan deteksi lokasi. Klik pada peta sebagai gantinya.',
            life: 6000,
        });

        return;
    }

    menungguLokasi.value = true;

    navigator.geolocation.getCurrentPosition(
        (posisi) => {
            menungguLokasi.value = false;
            geserAcuan({ lat: posisi.coords.latitude, lng: posisi.coords.longitude });
        },
        (kesalahan) => {
            menungguLokasi.value = false;
            toast.add({
                severity: 'warn',
                summary: 'Lokasi tidak terdeteksi',
                detail: kesalahan.code === kesalahan.PERMISSION_DENIED
                    ? 'Izin lokasi ditolak. Klik pada peta untuk menentukan titik acuan.'
                    : 'Deteksi lokasi memerlukan koneksi HTTPS. Klik pada peta sebagai gantinya.',
                life: 7000,
            });
        },
        { timeout: 10000 },
    );
}

/*
 * Pencarian berjalan sendiri saat penyaring diubah, tanpa menunggu tombol
 * Cari ditekan. Tombolnya tetap ada untuk yang terbiasa menekannya, dan untuk
 * mengulang pencarian setelah galat.
 *
 * Penundaan mencegah satu geseran slider atau satu kata yang diketik menjadi
 * puluhan permintaan. Mengetik diberi jeda lebih panjang daripada menggeser,
 * karena jeda antarhuruf jauh lebih pendek daripada jeda antargeseran.
 */
let penunda = null;

function cariTertunda(jeda) {
    clearTimeout(penunda);
    penunda = setTimeout(cari, jeda);
}

watch(() => filter.radius, () => cariTertunda(400));
watch(() => filter.q, () => cariTertunda(600));
watch(() => filter.harga_maks, () => cariTertunda(600));

onMounted(cari);
</script>

<template>
    <div>
        <!--
            Pita ditarik ke atas sebanyak ruang yang diberikan App.vue untuk
            bilah navigasi, lalu ruangnya dikembalikan sebagai padding di
            dalam. Hasilnya gradien menembus sampai tepi atas layar dan
            terlihat di belakang bilah yang melayang — hanya halaman ini yang
            melakukannya; halaman lain tetap memakai ruang itu apa adanya.
        -->
        <section class="pita-mesh -mt-20 text-white sm:-mt-24">
            <div class="mx-auto max-w-5xl px-4 pt-32 pb-24 text-center sm:pt-40 sm:pb-28">
                <h1 class="font-display text-4xl leading-[1.05] font-extrabold sm:text-6xl">
                    Kos di sekitar kampus
                </h1>

                <p class="mx-auto mt-4 max-w-xl text-white/75">
                    <template v-if="acuan">
                        Jarak dihitung dari {{ acuan.nama }}. Geser titik acuannya kapan saja
                        dengan mengklik peta.
                    </template>
                    <template v-else>
                        Klik di peta untuk menentukan dari mana jarak dihitung.
                    </template>
                </p>

                <div class="mt-8 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="tipe in pilihanTipe"
                        :key="tipe.label"
                        type="button"
                        class="pil"
                        :class="filter.tipe_kos === tipe.value && 'pil--aktif'"
                        :aria-pressed="filter.tipe_kos === tipe.value"
                        @click="pilihTipe(tipe.value)"
                    >
                        {{ tipe.label }}
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="meter in pilihanRadius"
                        :key="meter"
                        type="button"
                        class="pil"
                        :class="filter.radius === meter && 'pil--aktif'"
                        :aria-pressed="filter.radius === meter"
                        @click="pilihRadius(meter)"
                    >
                        {{ meter < 1000 ? `${meter} m` : `${meter / 1000} km` }}
                    </button>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 pb-10">
            <!--
                Kartu pencarian mengambang menimpa tepi pita, mengikuti
                referensi. Efeknya bukan sekadar hiasan: ia menandai bahwa
                alat pencarian adalah bagian dari pita atas, bukan baris
                penyaring biasa di atas daftar.
            -->
            <section
                class="relative z-10 -mt-16 bg-white p-5 shadow-[0_24px_50px_-20px_rgba(22,35,63,0.5)] sm:p-6"
                aria-label="Pencarian kos"
            >
                <div class="grid gap-5 lg:grid-cols-[1fr_auto]">
                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="cari">
                        <IconField class="sm:col-span-2">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filter.q"
                                placeholder="Nama kos atau nama jalan"
                                class="w-full"
                                aria-label="Cari nama kos atau alamat"
                            />
                        </IconField>

                        <InputNumber
                            v-model="filter.harga_maks"
                            mode="currency"
                            currency="IDR"
                            locale="id-ID"
                            :max-fraction-digits="0"
                            :step="100000"
                            placeholder="Harga maksimum"
                            class="w-full"
                            input-class="w-full"
                            aria-label="Harga maksimum per bulan"
                        />

                        <div class="flex gap-2">
                            <Button type="submit" label="Cari kos" class="grow" :loading="memuat" />
                            <Button
                                type="button"
                                severity="secondary"
                                outlined
                                icon="pi pi-map-marker"
                                aria-label="Pakai lokasi saya"
                                :loading="menungguLokasi"
                                @click="pakaiLokasiSaya"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <div class="flex items-baseline justify-between gap-4">
                                <label for="radius" class="text-sm text-[color:var(--color-tinta)]/70">
                                    Atur radius lebih halus
                                </label>
                                <span class="font-display font-semibold">
                                    {{ (filter.radius / 1000).toFixed(1) }} km
                                </span>
                            </div>
                            <Slider
                                id="radius"
                                v-model="filter.radius"
                                :min="200"
                                :max="konfigurasi.radius_maksimum ?? 10000"
                                :step="100"
                                class="mt-2.5 w-full"
                            />
                        </div>
                    </form>

                    <div
                        class="flex items-center gap-4 border-t border-[color:var(--color-garis)] pt-4 lg:w-44 lg:flex-col lg:items-start lg:justify-center lg:border-l lg:border-t-0 lg:pt-0 lg:pl-6"
                    >
                        <p class="font-display text-5xl leading-none font-extrabold text-[color:var(--color-emas)]">
                            <template v-if="memuat">&mdash;</template>
                            <template v-else>{{ daftarKos.length }}</template>
                        </p>
                        <p class="text-sm text-[color:var(--color-tinta)]/65">
                            kos terjangkau dalam radius {{ (filter.radius / 1000).toFixed(1) }} km
                        </p>
                    </div>
                </div>
            </section>

            <!--
                Pemilih tampilan khusus layar kecil. Di ponsel, daftar dan peta
                bergantian memakai seluruh lebar; di layar lebar keduanya
                tampil bersama dan pemilih ini disembunyikan.
            -->
            <div
                class="mt-6 inline-flex rounded-full border border-[color:var(--color-garis)] bg-white p-1 lg:hidden"
                role="group"
                aria-label="Pilih tampilan"
            >
                <button
                    v-for="pilihan in [
                        { nilai: 'daftar', label: 'Daftar', ikon: 'pi-list' },
                        { nilai: 'peta', label: 'Peta', ikon: 'pi-map' },
                    ]"
                    :key="pilihan.nilai"
                    type="button"
                    class="flex items-center gap-2 rounded-full px-4 py-1.5 text-sm transition"
                    :class="tampilan === pilihan.nilai
                        ? 'bg-[color:var(--color-tinta)] font-semibold text-white'
                        : 'text-[color:var(--color-tinta)]/70'"
                    :aria-pressed="tampilan === pilihan.nilai"
                    @click="tampilan = pilihan.nilai"
                >
                    <i :class="`pi ${pilihan.ikon} text-xs`" aria-hidden="true" />
                    {{ pilihan.label }}
                </button>
            </div>

            <div class="mt-4 grid gap-5 lg:grid-cols-[1fr_1.05fr]">
                <section
                    class="order-2 lg:order-1 lg:block"
                    :class="tampilan === 'peta' && 'hidden'"
                    aria-live="polite"
                >
                    <p class="mb-3 text-sm text-[color:var(--color-tinta)]/60">
                        <template v-if="memuat">Mencari kos...</template>
                        <template v-else-if="daftarKos.length">Terdekat lebih dulu</template>
                    </p>

                    <!-- Skeleton memakai bentuk kartu yang sama, sehingga tata
                         letak tidak melompat saat data datang. -->
                    <div v-if="memuat" class="grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="n in 4"
                            :key="n"
                            class="overflow-hidden bg-white shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)]"
                        >
                            <Skeleton height="10rem" border-radius="0" />
                            <div class="space-y-2.5 p-5">
                                <Skeleton width="70%" height="1.2rem" />
                                <Skeleton width="90%" height="0.8rem" />
                                <Skeleton width="100%" height="0.5rem" />
                                <Skeleton width="45%" height="1.2rem" />
                                <Skeleton width="30%" height="0.8rem" />
                            </div>
                        </div>
                    </div>

                    <div
                        v-else-if="daftarKos.length === 0"
                        class="bg-white p-8 text-center shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)]"
                    >
                        <p class="font-display text-lg font-semibold">Belum ada yang cocok</p>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-[color:var(--color-tinta)]/70">
                            Perbesar radius, naikkan batas harga, atau pindahkan titik acuan
                            dengan mengklik peta.
                        </p>
                        <Button
                            label="Perbesar radius jadi 2 km"
                            severity="secondary"
                            outlined
                            class="mt-4"
                            @click="pilihRadius(2000)"
                        />
                    </div>

                    <TransitionGroup
                        v-else
                        tag="div"
                        class="grid gap-5 sm:grid-cols-2"
                        enter-from-class="opacity-0"
                        enter-active-class="transition-opacity duration-200"
                    >
                        <KartuKos
                            v-for="kos in daftarKos"
                            :id="`kos-${kos.id}`"
                            :key="kos.id"
                            :kos="kos"
                            :radius="filter.radius"
                            :tersorot="kosTersorot === kos.id"
                            @sorot="kosTersorot = $event"
                        />
                    </TransitionGroup>
                </section>

                <section
                    class="order-1 overflow-hidden shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)] lg:sticky lg:top-28 lg:order-2 lg:block lg:h-[calc(100vh-9rem)]"
                    :class="tampilan === 'daftar' ? 'hidden lg:block' : 'h-[70vh]'"
                    aria-label="Peta sebaran kos"
                >
                    <PetaKos
                        :daftar-kos="daftarKos"
                        :acuan="acuan"
                        :radius="filter.radius"
                        :kos-tersorot="kosTersorot"
                        @pilih-kos="pilihDariPeta"
                        @geser-acuan="geserAcuan"
                    />
                </section>
            </div>
        </div>
    </div>
</template>
