<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
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
import MotifSulur from '../components/MotifSulur.vue';
import AngkaBerjalan from '../components/AngkaBerjalan.vue';

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
    harga_min: null,
    harga_maks: null,
    fasilitas: [],
    q: '',
});

/*
 * Pilihan fasilitas datang dari server, sama dengan yang dipakai formulir
 * pemilik. Penjelasannya di config/ekost.php.
 */
const fasilitasPilihan = konfigurasi.fasilitas ?? [];

// Panel penyaring lanjutan tertutup pada awalnya. Mockup menampilkannya
// terbuka, tetapi di sana isinya statis; di sini ia mendorong daftar hasil
// jauh ke bawah pada layar ponsel, dan yang dicari orang adalah hasilnya.
const filterTerbuka = ref(false);

function alihkanFasilitas(nama) {
    const posisi = filter.fasilitas.indexOf(nama);

    if (posisi === -1) {
        filter.fasilitas.push(nama);
    } else {
        filter.fasilitas.splice(posisi, 1);
    }

    cari();
}

function bersihkanFilter() {
    filter.tipe_kos = null;
    filter.harga_min = null;
    filter.harga_maks = null;
    filter.fasilitas = [];
    filter.q = '';
    cari();
}

const adaFilterAktif = computed(() =>
    filter.tipe_kos !== null
    || filter.harga_min !== null
    || filter.harga_maks !== null
    || filter.fasilitas.length > 0
    || filter.q !== '',
);

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

/**
 * Tiga kos bernilai terbaik dalam hasil pencarian saat ini.
 *
 * "Terbaik" di sini berarti rating tinggi pada harga rendah — dua hal yang
 * tidak bisa dibandingkan langsung, karena rating berskala 1 sampai 5
 * sedangkan harga ratusan ribu sampai jutaan. Keduanya disetarakan dulu ke
 * rentang 0 sampai 1 terhadap hasil pencarian yang sedang tampil, lalu
 * dijumlahkan dengan bobot sama.
 *
 * Penyetaraannya memakai hasil yang sedang tampil, bukan seluruh basis data.
 * "Termurah" pada radius 500 m memang harus berarti termurah di antara yang
 * muat di radius itu — bukan termurah se-Majene, yang bisa saja berada jauh
 * di luar jangkauan jalan kaki.
 *
 * DUA SYARAT YANG MENGGUGURKAN
 *
 * Kos tanpa satu pun ulasan tidak ikut dinilai. Tanpa itu, kos yang baru
 * didaftarkan dan belum pernah ditinggali siapa pun akan menang hanya karena
 * harganya murah, dan sorotan ini justru menyesatkan.
 *
 * Kos yang kamarnya penuh juga digugurkan. Merekomendasikan kamar yang tidak
 * bisa dipesan hanya membuang waktu orang yang mengkliknya.
 */
const idUnggulan = computed(() => {
    const layak = daftarKos.value.filter(
        (kos) => kos.tersedia && (kos.jumlah_ulasan ?? 0) > 0,
    );

    if (layak.length === 0) {
        return new Set();
    }

    const harga = layak.map((kos) => kos.harga_per_bulan ?? 0);
    const termurah = Math.min(...harga);
    const termahal = Math.max(...harga);

    // Bila seluruhnya seharga sama, sisi harga tidak membedakan apa pun dan
    // penilaiannya jatuh sepenuhnya pada rating.
    const rentang = termahal - termurah;

    const bernilai = layak
        .map((kos) => {
            const nilaiRating = (kos.rating_rata ?? 0) / 5;
            const nilaiHarga = rentang === 0
                ? 1
                : (termahal - (kos.harga_per_bulan ?? 0)) / rentang;

            return { id: kos.id, nilai: nilaiRating * 0.5 + nilaiHarga * 0.5 };
        })
        .sort((a, b) => b.nilai - a.nilai)
        .slice(0, 3);

    return new Set(bernilai.map((satu) => satu.id));
});
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
                harga_min: filter.harga_min ?? undefined,
                harga_maks: filter.harga_maks ?? undefined,
                fasilitas: filter.fasilitas.length ? filter.fasilitas : undefined,
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
watch(() => filter.harga_min, () => cariTertunda(600));
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
        <section class="pita-mesh relative -mt-20 overflow-hidden text-white sm:-mt-24">
            <MotifSulur />
            <!--
                pt harus melampaui tinggi bilah navigasi yang melayang
                (80 px di ponsel, 96 px di layar lebar). Sisanya adalah jarak
                baca yang sebenarnya: sekitar 24 px, bukan 48.
            -->
            <!-- relative z-10 menaikkan isi di atas motif yang digambar
                 sebagai lapisan absolut di belakangnya. -->
            <!-- Isi pita sengaja tidak ikut dilebarkan sampai 1600 px:
                 paragraf sepanjang itu melelahkan dibaca. Yang melebar hanya
                 latarnya, yang memang membentang penuh. -->
            <!--
                Jarak bawah pita dan tarikan ke atas kartu pencarian berubah
                bersama-sama, selisihnya tetap.

                Pita menutupi kartu sedalam nilai tarikan kartunya. Menambah
                tarikan saja akan membuat pita naik menutupi deretan pil
                radius; menambah jarak bawah pita saja hanya membuat pitanya
                lebih tinggi tanpa menutupi apa pun. Keduanya dinaikkan dengan
                angka yang sama, sehingga jarak antara pil radius dan tepi
                atas kartu tidak berubah sedikit pun — 32px di layar lebar,
                16px di ponsel.
            -->
            <div class="relative z-10 mx-auto max-w-6xl px-4 pt-26 pb-24 text-center sm:pt-30 sm:pb-[8.5rem]">
                <!-- Judul disingkap dari bawah ke atas, seperti pita yang
                     digulung terbuka. Gerakan itu dipakai hanya di sini:
                     satu gerakan yang berbeda pada satu elemen terpenting
                     lebih terasa daripada enam gerakan yang semuanya unik. -->
                <h1
                    v-muncul:selubung
                    class="font-display text-5xl leading-[1.03] font-extrabold sm:text-6xl lg:text-7xl"
                >
                    Kos di sekitar kampus
                </h1>

                <p
                    v-muncul="{ nama: 'naik', jeda: 180 }"
                    class="mx-auto mt-3 max-w-2xl text-base text-white/75 sm:text-lg"
                >
                    <template v-if="acuan">
                        Jarak dihitung dari {{ acuan.nama }}. Geser titik acuannya kapan saja
                        dengan mengklik peta.
                    </template>
                    <template v-else>
                        Klik di peta untuk menentukan dari mana jarak dihitung.
                    </template>
                </p>

                <!-- Pil-pil penyaring mengembang satu per satu dari kiri ke
                     kanan. Jedanya berurutan supaya terbaca sebagai satu
                     baris yang terisi, bukan empat tombol yang kebetulan
                     bergerak bersamaan. -->
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="(tipe, urutan) in pilihanTipe"
                        :key="tipe.label"
                        v-muncul="{ nama: 'mekar', jeda: 320 + urutan * 70 }"
                        type="button"
                        class="pil"
                        :class="filter.tipe_kos === tipe.value && 'pil--aktif'"
                        :aria-pressed="filter.tipe_kos === tipe.value"
                        @click="pilihTipe(tipe.value)"
                    >
                        {{ tipe.label }}
                    </button>
                </div>

                <div class="mt-2 flex flex-wrap justify-center gap-2">
                    <button
                        v-for="(meter, urutan) in pilihanRadius"
                        :key="meter"
                        v-muncul="{ nama: 'mekar', jeda: 600 + urutan * 70 }"
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

        <!-- Lebar isi 1800 px dengan jarak tepi yang ditekan serendah mungkin:
             16 px di ponsel, 24 px di layar lebar. Monitor 1920 px kini
             menyisakan sekitar 60 px di tiap sisi, bukan 300 px seperti pada
             batas 7xl bawaan Tailwind.

             Jarak tepi tidak dihilangkan sama sekali karena bayangan kartu
             butuh ruang untuk jatuh; tanpa itu, kartunya tampak terpotong di
             tepi layar. -->
        <div class="mx-auto max-w-[1800px] px-4 pb-8 sm:px-5 lg:px-6">
            <!--
                Kartu pencarian mengambang menimpa tepi pita, mengikuti
                referensi. Efeknya bukan sekadar hiasan: ia menandai bahwa
                alat pencarian adalah bagian dari pita atas, bukan baris
                penyaring biasa di atas daftar.

                Sudut atasnya dibuat lebih besar daripada sudut bawahnya.
                Dua sudut atas itulah yang terlihat naik menimpa pita, dan
                lengkung yang lebih lebar di sana membuat bidang putih ini
                terbaca sebagai permukaan yang muncul dari balik pita —
                bukan sebagai kotak yang kebetulan ditempelkan di atasnya.
                Sudut bawahnya tetap 12px, sama dengan kartu kos.
            -->
            <section
                v-muncul="{ nama: 'naik', jeda: 240 }"
                class="relative z-10 -mt-20 rounded-t-[20px] rounded-b-xl bg-white p-4 shadow-[0_24px_50px_-20px_rgba(22,35,63,0.5)] sm:-mt-26 sm:p-5"
                aria-label="Pencarian kos"
            >
                <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="cari">
                        <IconField class="sm:col-span-2">
                            <InputIcon class="pi pi-search" />
                            <InputText
                                v-model="filter.q"
                                placeholder="Nama kos atau nama jalan"
                                class="w-full"
                                size="large"
                                aria-label="Cari nama kos atau alamat"
                            />
                        </IconField>

                        <!-- Rentang harga, mengikuti mockup hal. 59 yang
                             memberi batas bawah dan batas atas. Sebelumnya
                             hanya ada batas atas, sehingga orang yang ingin
                             menghindari kos termurah tidak punya cara
                             menyatakannya. -->
                        <div class="grid grid-cols-2 gap-2">
                            <InputNumber
                                v-model="filter.harga_min"
                                mode="currency"
                                currency="IDR"
                                locale="id-ID"
                                :max-fraction-digits="0"
                                :step="100000"
                                placeholder="Harga minimum"
                                class="w-full"
                                size="large"
                                input-class="w-full"
                                aria-label="Harga minimum per bulan"
                            />
                            <InputNumber
                                v-model="filter.harga_maks"
                                mode="currency"
                                currency="IDR"
                                locale="id-ID"
                                :max-fraction-digits="0"
                                :step="100000"
                                placeholder="Harga maksimum"
                                class="w-full"
                                size="large"
                                input-class="w-full"
                                aria-label="Harga maksimum per bulan"
                            />
                        </div>

                        <div class="flex gap-2">
                            <Button type="submit" label="Cari kos" size="large" class="grow" :loading="memuat" />
                            <Button
                                type="button"
                                severity="secondary"
                                outlined
                                size="large"
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
                                <span class="font-display text-sm font-semibold">
                                    {{ (filter.radius / 1000).toFixed(1) }} km
                                </span>
                            </div>
                            <Slider
                                id="radius"
                                v-model="filter.radius"
                                :min="200"
                                :max="konfigurasi.radius_maksimum ?? 10000"
                                :step="100"
                                class="mt-2 w-full"
                            />
                        </div>

                        <!--
                            Penyaring fasilitas, mengikuti mockup hal. 59.
                            Dilipat karena sepuluh kotak centang yang selalu
                            terbuka mendorong daftar hasil jauh ke bawah layar
                            ponsel — dan hasilnya yang dicari orang, bukan
                            penyaringnya.
                        -->
                        <div class="sm:col-span-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-[color:var(--color-indigo)]"
                                    :aria-expanded="filterTerbuka"
                                    @click="filterTerbuka = !filterTerbuka"
                                >
                                    <i
                                        class="pi text-xs transition-transform"
                                        :class="filterTerbuka ? 'pi-chevron-up' : 'pi-chevron-down'"
                                        aria-hidden="true"
                                    />
                                    Fasilitas
                                    <span
                                        v-if="filter.fasilitas.length"
                                        class="rounded-full bg-[color:var(--color-indigo)] px-2 py-0.5 text-xs text-white"
                                    >
                                        {{ filter.fasilitas.length }}
                                    </span>
                                </button>

                                <button
                                    v-if="adaFilterAktif"
                                    type="button"
                                    class="text-sm text-[color:var(--color-tinta)]/60 underline hover:text-[color:var(--color-maroon)]"
                                    @click="bersihkanFilter"
                                >
                                    Bersihkan penyaring
                                </button>
                            </div>

                            <Transition
                                enter-active-class="transition duration-200 ease-out"
                                leave-active-class="transition duration-150 ease-in"
                                enter-from-class="opacity-0 -translate-y-1"
                                leave-to-class="opacity-0 -translate-y-1"
                            >
                                <div v-if="filterTerbuka" class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="nama in fasilitasPilihan"
                                        :key="nama"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-sm transition"
                                        :class="filter.fasilitas.includes(nama)
                                            ? 'border-[color:var(--color-indigo)] bg-[color:var(--color-indigo)] text-white'
                                            : 'border-[color:var(--color-garis)] hover:bg-[color:var(--color-kertas)]'"
                                        :aria-pressed="filter.fasilitas.includes(nama)"
                                        @click="alihkanFasilitas(nama)"
                                    >
                                        {{ nama }}
                                    </button>
                                </div>
                            </Transition>
                        </div>
                    </form>

                    <div
                        class="flex items-center gap-3 border-t border-[color:var(--color-garis)] pt-3 lg:w-44 lg:flex-col lg:items-start lg:justify-center lg:border-l lg:border-t-0 lg:pt-0 lg:pl-6"
                    >
                        <!-- Angkanya berjalan naik, bukan berganti seketika.
                             Ini satu-satunya gerakan yang menjawab tindakan
                             pengguna: geser radius, lalu lihat angkanya
                             bergerak — bukti bahwa pencariannya berjalan. -->
                        <p class="font-display text-6xl leading-none font-extrabold text-[color:var(--color-emas)]">
                            <template v-if="memuat">&mdash;</template>
                            <AngkaBerjalan v-else :nilai="daftarKos.length" />
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
                v-muncul="{ nama: 'kiri', jeda: 320 }"
                class="mt-5 inline-flex rounded-full border border-[color:var(--color-garis)] bg-white p-1 lg:hidden"
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

            <!-- Daftar diberi porsi lebih lebar daripada peta: kartunya yang
                 memuat informasi yang dibandingkan, sedangkan peta cukup
                 menunjukkan sebarannya. -->
            <div class="mt-4 grid gap-4 lg:grid-cols-[1.35fr_1fr]">
                <section
                    class="order-2 lg:order-1 lg:block"
                    :class="tampilan === 'peta' && 'hidden'"
                    aria-live="polite"
                >
                    <p class="mb-2 text-sm text-[color:var(--color-tinta)]/60">
                        <template v-if="memuat">Mencari kos...</template>
                        <template v-else-if="daftarKos.length">Terdekat lebih dulu</template>
                    </p>

                    <!-- Rangka tiruan mengikuti susunan mozaik yang sama,
                         sehingga tata letak tidak melompat saat data datang. -->
                    <div v-if="memuat" class="kisi-mozaik grid gap-5 sm:grid-cols-2">
                        <div v-for="n in 5" :key="n" class="overflow-hidden rounded-xl">
                            <Skeleton width="100%" height="100%" border-radius="12px" />
                        </div>
                    </div>

                    <div
                        v-else-if="daftarKos.length === 0"
                        v-muncul:mekar
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

                    <!--
                        TransitionGroup tinggal mengurus perpindahan posisi
                        kartu saat hasil pencarian berubah; kemunculannya
                        diserahkan ke v-muncul agar tidak ada dua mekanisme
                        yang sama-sama mengatur opacity.

                        Jedanya dibatasi tujuh langkah. Tanpa batas itu,
                        kartu kedua puluh menunggu 1,4 detik — yang sudah
                        bukan lagi riak, melainkan penundaan.
                    -->
                    <TransitionGroup
                        v-else
                        tag="div"
                        class="kisi-mozaik grid gap-5 sm:grid-cols-2"
                        move-class="transition-transform duration-300 ease-out"
                    >
                        <KartuKos
                            v-for="(kos, urutan) in daftarKos"
                            :id="`kos-${kos.id}`"
                            :key="kos.id"
                            v-muncul="{ nama: 'naik', jeda: Math.min(urutan, 7) * 70 }"
                            :kos="kos"
                            :unggulan="idUnggulan.has(kos.id)"
                            :radius="filter.radius"
                            :tersorot="kosTersorot === kos.id"
                            @sorot="kosTersorot = $event"
                        />
                    </TransitionGroup>
                </section>

                <!-- Peta mengarah dari buram menjadi jelas. Gerakan itu cocok
                     khusus untuk peta: petaknya memang datang sebagai bidang
                     warna lalu menjadi terbaca saat petaknya selesai diunduh. -->
                <section
                    v-muncul="{ nama: 'kabur', jeda: 160 }"
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

<style scoped>
/*
 * SUSUNAN MOZAIK
 *
 * Pola berulang tiap lima kartu: dua kartu lebar di baris atas, tiga kartu
 * sempit di bawahnya. Dikerjakan dengan kisi enam kolom — dua kartu lebar
 * mengambil tiga kolom masing-masing, tiga kartu sempit mengambil dua.
 *
 * Pengulangan tiap lima inilah yang membedakannya dari referensi aslinya.
 * Di sana jumlah kartunya memang lima dan ditentukan penyusunnya; di sini
 * jumlahnya bergantung hasil pencarian dan bisa nol sampai dua puluh empat.
 * Pola yang tidak berulang akan kehabisan bentuk pada kartu keenam.
 *
 * Hanya berlaku mulai 1280px. Di bawah itu kolomnya terlalu sempit: kartu
 * selebar dua dari enam kolom pada layar 1024px tinggal 150px, dan harganya
 * tidak lagi muat satu baris. Layar sempit kembali ke dua kolom biasa.
 */
@media (min-width: 1280px) {
    .kisi-mozaik {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    .kisi-mozaik > :nth-child(5n + 1),
    .kisi-mozaik > :nth-child(5n + 2) {
        grid-column: span 3;
        aspect-ratio: 16 / 10;
    }

    .kisi-mozaik > :nth-child(5n + 3),
    .kisi-mozaik > :nth-child(5n + 4),
    .kisi-mozaik > :nth-child(5n + 5) {
        grid-column: span 2;
        aspect-ratio: 4 / 3;
    }

    /*
     * Sisa yang tidak genap lima dilebarkan agar barisnya tetap penuh.
     *
     * Tanpa ini, baris terakhir berakhir bolong — dan baris bolong terlihat
     * seperti kartu yang gagal dimuat, bukan seperti daftar yang habis.
     *
     * Tiga kemungkinan sisa yang perlu ditangani. Sisa dua kartu sudah genap
     * sendiri (3 + 3 kolom), sisa nol jelas tidak perlu apa-apa:
     *
     *   sisa 1 → kartu ke-(5n+1) sendirian  → melebar enam kolom
     *   sisa 3 → kartu ke-(5n+3) sendirian di barisnya → melebar enam kolom
     *   sisa 4 → kartu ke-(5n+4) menyisakan dua kolom → melebar jadi empat
     *
     * Kartu yang melebar penuh memakai tinggi tetap, bukan perbandingan sisi.
     * Perbandingan 4/3 pada lebar 1200px menghasilkan kartu setinggi 900px —
     * satu kos memakan seluruh layar.
     */
    .kisi-mozaik > :nth-child(5n + 1):last-child,
    .kisi-mozaik > :nth-child(5n + 3):last-child {
        grid-column: span 6;
        aspect-ratio: auto;
        height: 18rem;
    }

    .kisi-mozaik > :nth-child(5n + 4):last-child {
        grid-column: span 4;
    }
}

/* Di luar susunan mozaik, kartunya tetap butuh bentuk — tanpa aspect-ratio
   tingginya mengikuti isi dan seluruh fotonya gepeng. */
@media (max-width: 1279.98px) {
    .kisi-mozaik > * {
        aspect-ratio: 4 / 3;
    }
}
</style>
