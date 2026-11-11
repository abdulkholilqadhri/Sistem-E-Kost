<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Penanda berbentuk pil yang meluncur di belakang sederet tautan.
 *
 * Butir yang hendak ditandai cukup diberi atribut data-pil berisi nilai yang
 * sama dengan prop aktif. Komponen ini tidak tahu apa-apa soal rute atau
 * menu — ia hanya mencari butir itu, mengukurnya, lalu menaruh pil di
 * belakangnya.
 *
 * KESAN KARETNYA
 *
 * Pil tidak digeser sebagai satu kotak. Kedua tepinya dianimasikan sendiri-
 * sendiri: tepi yang searah tujuan berangkat lebih dulu, tepi lawannya
 * menyusul sesudah jeda singkat. Selama jeda itu pilnya meregang lebih
 * panjang daripada tujuannya, lalu mengerut ketika tepi belakang tiba.
 * Hanya itu sumber kesan karetnya — tidak ada pegas atau pantulan.
 *
 * Animasinya memakai requestAnimationFrame, bukan transisi CSS, karena
 * transisi CSS pada posisi dan ukuran akan selalu bergerak serempak dan
 * tidak bisa diberi jeda yang berbeda untuk tiap tepi.
 *
 * DUA SUMBU
 *
 * Deretan mendatar menganimasikan tepi kiri dan kanan; deretan tegak, tepi
 * atas dan bawah. Selebihnya sama persis — durasi, jeda, kurva, dan cara
 * menentukan arah. Di dalam kode keduanya disebut tepi "awal" dan "akhir"
 * supaya satu jalur logika melayani kedua sumbu.
 */
const props = defineProps({
    aktif: { type: [String, Number], default: null },

    /** 'mendatar' untuk deretan menyamping, 'tegak' untuk deretan bersusun. */
    arah: {
        type: String,
        default: 'mendatar',
        validator: (nilai) => ['mendatar', 'tegak'].includes(nilai),
    },

    /** Warna latar pil, menyesuaikan permukaan tempat deretannya berada. */
    warna: { type: String, default: 'var(--color-kertas)' },

    /** Jari-jari sudut, menyesuaikan bentuk butir yang ditandai. */
    jari: { type: String, default: '0.5rem' },
});

/** Lama tiap tepi menempuh perjalanannya. */
const DURASI_MS = 320;

/**
 * Seberapa lama tepi belakang tertinggal. Cukup untuk terlihat meregang,
 * tetapi tidak sampai terasa seperti dua benda yang bergerak terpisah.
 */
const JEDA_MS = 90;

const jalur = ref(null);
const awal = ref(0);
const akhir = ref(0);
const tampak = ref(false);

const tegak = computed(() => props.arah === 'tegak');

/*
 * Pil direntangkan penuh di sumbu silang, sehingga yang perlu diatur hanya
 * posisi dan panjangnya di sumbu gerak.
 */
const gayaPil = computed(() => {
    const panjang = `${Math.max(0, akhir.value - awal.value)}px`;
    const posisi = `${awal.value}px`;

    return {
        backgroundColor: props.warna,
        borderRadius: props.jari,
        ...(tegak.value ? { top: posisi, height: panjang } : { left: posisi, width: panjang }),
    };
});

let bingkai = null;
let pengamat = null;

function inginGerakDikurangi() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function jepit(nilai) {
    return Math.min(1, Math.max(0, nilai));
}

// Melandai di akhir, selaras dengan kurva animasi lain di proyek ini.
function landai(maju) {
    return 1 - Math.pow(1 - maju, 3);
}

function sembunyikan() {
    cancelAnimationFrame(bingkai);
    tampak.value = false;
}

function luncurkan(keAwal, keAkhir) {
    cancelAnimationFrame(bingkai);

    const dariAwal = awal.value;
    const dariAkhir = akhir.value;

    /*
     * Arah ditentukan dari titik tengah, bukan dari salah satu tepi. Butir
     * tidak selalu sama panjang: berpindah ke butir panjang di depan bisa
     * membuat tepi awalnya mundur sedikit, dan menilai dari tepi awal saja
     * akan membuat tepi yang salah berangkat lebih dulu.
     */
    const maju = (keAwal + keAkhir) / 2 > (dariAwal + dariAkhir) / 2;
    const jedaAwal = maju ? JEDA_MS : 0;
    const jedaAkhir = maju ? 0 : JEDA_MS;

    const mulai = performance.now();

    function langkah(sekarang) {
        const lewat = sekarang - mulai;
        const majuAwal = jepit((lewat - jedaAwal) / DURASI_MS);
        const majuAkhir = jepit((lewat - jedaAkhir) / DURASI_MS);

        awal.value = dariAwal + (keAwal - dariAwal) * landai(majuAwal);
        akhir.value = dariAkhir + (keAkhir - dariAkhir) * landai(majuAkhir);

        if (majuAwal < 1 || majuAkhir < 1) {
            bingkai = requestAnimationFrame(langkah);
        }
    }

    bingkai = requestAnimationFrame(langkah);
}

/**
 * Mengukur butir aktif dan memindahkan pil ke sana.
 *
 * Pil langsung melompat tanpa animasi bila diminta, bila ia sebelumnya
 * tersembunyi, atau bila pengguna meminta pengurangan gerak. Pil yang baru
 * muncul tidak punya posisi asal yang berarti — meluncur dari posisi lama
 * yang sudah usang hanya akan terlihat seperti salah tempat.
 */
function ukur(langsung = false) {
    const wadah = jalur.value;

    if (!wadah) {
        return;
    }

    if (props.aktif === null) {
        sembunyikan();

        return;
    }

    const sasaran = wadah.querySelector(`[data-pil="${CSS.escape(String(props.aktif))}"]`);

    if (!sasaran) {
        sembunyikan();

        return;
    }

    const kotakWadah = wadah.getBoundingClientRect();
    const kotakSasaran = sasaran.getBoundingClientRect();

    /*
     * Deretan yang sedang tersembunyi (display: none) mengukur nol. Pil
     * selebar nol yang terlanjur ditaruh di pojok akan terlihat melesat
     * masuk begitu deretannya tampil lagi. Lebih baik disembunyikan, lalu
     * dilompatkan langsung saat ResizeObserver melihat deretannya muncul.
     */
    if (kotakSasaran.width === 0 || kotakSasaran.height === 0) {
        sembunyikan();

        return;
    }

    const tujuanAwal = tegak.value
        ? kotakSasaran.top - kotakWadah.top
        : kotakSasaran.left - kotakWadah.left;
    const tujuanAkhir = tegak.value
        ? kotakSasaran.bottom - kotakWadah.top
        : kotakSasaran.right - kotakWadah.left;

    if (langsung || !tampak.value || inginGerakDikurangi()) {
        cancelAnimationFrame(bingkai);
        awal.value = tujuanAwal;
        akhir.value = tujuanAkhir;
        tampak.value = true;

        return;
    }

    luncurkan(tujuanAwal, tujuanAkhir);
}

/*
 * Diukur di dalam nextTick karena kelas aktif pada butir baru terpasang
 * pada siklus render berikutnya. Mengukur sekarang berarti mengukur butir
 * dengan gaya lamanya.
 */
watch(
    () => props.aktif,
    () => nextTick(() => ukur()),
);

onMounted(() => {
    ukur(true);

    // Perubahan ukuran — layar diubah lebarnya, menu berganti karena peran —
    // tidak dianimasikan. Pil yang meluncur sendiri tanpa dipicu pengguna
    // terasa seperti kesalahan, bukan umpan balik.
    pengamat = new ResizeObserver(() => ukur(true));
    pengamat.observe(jalur.value);

    // Fonta display dimuat belakangan. Sebelum ia tiba, ukuran teksnya
    // meleset beberapa piksel dan pil akan tampak sedikit tidak pas.
    document.fonts?.ready.then(() => ukur(true));
});

onBeforeUnmount(() => {
    cancelAnimationFrame(bingkai);
    pengamat?.disconnect();
});
</script>

<template>
    <div ref="jalur" class="relative">
        <span
            class="pointer-events-none absolute transition-opacity duration-200"
            :class="[tegak ? 'inset-x-0' : 'inset-y-0', tampak ? 'opacity-100' : 'opacity-0']"
            :style="gayaPil"
            aria-hidden="true"
        />
        <slot />
    </div>
</template>
