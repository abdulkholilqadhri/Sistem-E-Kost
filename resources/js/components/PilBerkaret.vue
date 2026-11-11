<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
 * transisi CSS pada left dan width akan selalu bergerak serempak dan tidak
 * bisa diberi jeda yang berbeda untuk tiap tepi.
 */
const props = defineProps({
    aktif: { type: [String, Number], default: null },
});

/** Lama tiap tepi menempuh perjalanannya. */
const DURASI_MS = 320;

/**
 * Seberapa lama tepi belakang tertinggal. Cukup untuk terlihat meregang,
 * tetapi tidak sampai terasa seperti dua benda yang bergerak terpisah.
 */
const JEDA_MS = 90;

const jalur = ref(null);
const kiri = ref(0);
const kanan = ref(0);
const tampak = ref(false);

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

function luncurkan(keKiri, keKanan) {
    cancelAnimationFrame(bingkai);

    const dariKiri = kiri.value;
    const dariKanan = kanan.value;

    /*
     * Arah ditentukan dari titik tengah, bukan dari salah satu tepi. Butir
     * menu tidak sama lebar: berpindah ke butir lebar di sebelah kanan bisa
     * membuat tepi kirinya mundur sedikit, dan menilai dari tepi kiri saja
     * akan membuat tepi yang salah berangkat lebih dulu.
     */
    const keArahKanan = (keKiri + keKanan) / 2 > (dariKiri + dariKanan) / 2;
    const jedaKiri = keArahKanan ? JEDA_MS : 0;
    const jedaKanan = keArahKanan ? 0 : JEDA_MS;

    const mulai = performance.now();

    function langkah(sekarang) {
        const lewat = sekarang - mulai;
        const majuKiri = jepit((lewat - jedaKiri) / DURASI_MS);
        const majuKanan = jepit((lewat - jedaKanan) / DURASI_MS);

        kiri.value = dariKiri + (keKiri - dariKiri) * landai(majuKiri);
        kanan.value = dariKanan + (keKanan - dariKanan) * landai(majuKanan);

        if (majuKiri < 1 || majuKanan < 1) {
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
     * Menu mendatar hanya tampil dari lg ke atas. Mengukurnya saat
     * tersembunyi menghasilkan lebar nol di pojok kiri, dan pil selebar nol
     * itu akan terlihat melesat masuk begitu layar diperlebar. Lebih baik
     * disembunyikan, lalu dilompatkan langsung saat ResizeObserver melihat
     * menunya muncul.
     */
    if (kotakSasaran.width === 0) {
        sembunyikan();

        return;
    }

    const tujuanKiri = kotakSasaran.left - kotakWadah.left;
    const tujuanKanan = kotakSasaran.right - kotakWadah.left;

    if (langsung || !tampak.value || inginGerakDikurangi()) {
        cancelAnimationFrame(bingkai);
        kiri.value = tujuanKiri;
        kanan.value = tujuanKanan;
        tampak.value = true;

        return;
    }

    luncurkan(tujuanKiri, tujuanKanan);
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

    // Perubahan ukuran — layar diperlebar, menu berganti karena peran — tidak
    // dianimasikan. Pil yang meluncur sendiri tanpa dipicu pengguna terasa
    // seperti kesalahan, bukan umpan balik.
    pengamat = new ResizeObserver(() => ukur(true));
    pengamat.observe(jalur.value);

    // Fonta display dimuat belakangan. Sebelum ia tiba, lebar teksnya meleset
    // beberapa piksel dan pil akan tampak sedikit tidak pas.
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
            class="pointer-events-none absolute inset-y-0 rounded-lg bg-[color:var(--color-kertas)] transition-opacity duration-200"
            :class="tampak ? 'opacity-100' : 'opacity-0'"
            :style="{ left: `${kiri}px`, width: `${Math.max(0, kanan - kiri)}px` }"
            aria-hidden="true"
        />
        <slot />
    </div>
</template>
