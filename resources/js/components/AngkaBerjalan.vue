<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Menampilkan angka yang berjalan naik dari nol ke nilainya.
 *
 * Dipakai untuk jumlah kos yang ditemukan. Angka yang berjalan membuat
 * hubungan sebab-akibat terasa: pengguna menggeser radius, lalu melihat
 * angkanya bergerak — bukan tiba-tiba berganti dan menyisakan keraguan
 * apakah pencariannya benar-benar berjalan.
 *
 * Durasinya pendek dengan sengaja. Angka yang berjalan terlalu lama
 * berhenti menjadi umpan balik dan mulai menjadi penundaan.
 */
const props = defineProps({
    nilai: { type: Number, default: 0 },
    durasi: { type: Number, default: 650 },
});

const tampil = ref(0);
let bingkai = null;

function inginGerakDikurangi() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function jalankan(dari, ke) {
    cancelAnimationFrame(bingkai);

    if (inginGerakDikurangi() || props.durasi <= 0) {
        tampil.value = ke;

        return;
    }

    const mulai = performance.now();

    function langkah(sekarang) {
        const maju = Math.min(1, (sekarang - mulai) / props.durasi);

        // Melandai di akhir, selaras dengan kurva animasi masuk lainnya.
        const landai = 1 - Math.pow(1 - maju, 3);

        tampil.value = Math.round(dari + (ke - dari) * landai);

        if (maju < 1) {
            bingkai = requestAnimationFrame(langkah);
        }
    }

    bingkai = requestAnimationFrame(langkah);
}

watch(
    () => props.nilai,
    (baru, lama) => jalankan(lama ?? 0, baru),
    { immediate: true },
);

onBeforeUnmount(() => cancelAnimationFrame(bingkai));
</script>

<template>
    <span>{{ tampil }}</span>
</template>
