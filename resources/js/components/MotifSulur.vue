<script setup>
import { useId } from 'vue';

/**
 * Motif sulur berulang untuk latar pita.
 *
 * Digambar sendiri sebagai SVG, bukan memakai gambar stok. Selain soal hak
 * pakai, pola vektor yang dibangkitkan begini berukuran beberapa ratus bita,
 * tetap tajam di layar beresolusi tinggi berapa pun, dan warnanya dapat
 * diikat ke palet aplikasi alih-alih dikunci di dalam berkas gambar.
 *
 * Susunan ubinnya: satu satuan motif di tengah, dan satu di setiap sudut.
 * Satuan di sudut terpotong seperempat, dan potongannya bertemu dengan
 * potongan dari ubin sebelahnya — itu yang membuat sambungannya tidak
 * terlihat dan sulurnya tampak saling mengait.
 */
const props = defineProps({
    warnaSulur: { type: String, default: 'rgba(255, 255, 255, 0.16)' },
    warnaRosette: { type: String, default: 'rgba(200, 146, 42, 0.42)' },
    ukuranUbin: { type: Number, default: 128 },
});

// Id unik per pemakaian. Bila komponen ini dipakai dua kali pada satu
// halaman dan id-nya sama, pemakaian kedua akan merujuk ke definisi pola
// milik yang pertama.
const idPola = `sulur-${useId()}`;

const tengah = props.ukuranUbin / 2;

/** Empat sulur yang memancar dari titik pusat satuan motif. */
const sudutSulur = [0, 90, 180, 270];

/** Delapan kelopak rosette. */
const sudutKelopak = [0, 45, 90, 135, 180, 225, 270, 315];
</script>

<template>
    <svg
        class="motif-sulur"
        aria-hidden="true"
        focusable="false"
        xmlns="http://www.w3.org/2000/svg"
    >
        <defs>
            <!-- Satu satuan motif, digambar berpusat di titik (0,0). -->
            <g :id="`${idPola}-satuan`">
                <g
                    fill="none"
                    :stroke="warnaSulur"
                    stroke-width="2.6"
                    stroke-linecap="round"
                >
                    <path
                        v-for="sudut in sudutSulur"
                        :key="sudut"
                        :transform="`rotate(${sudut})`"
                        d="M 0 -9
                           C 0 -24, 11 -32, 21 -29
                           C 30 -27, 32 -16, 24 -14
                           C 18 -12.6, 16 -19.5, 21.5 -20.5"
                    />
                </g>

                <g :fill="warnaRosette">
                    <path
                        v-for="sudut in sudutKelopak"
                        :key="sudut"
                        :transform="`rotate(${sudut})`"
                        d="M 0 -3.4
                           C 2.6 -5.8, 6.2 -7.6, 7.4 -11.6
                           C 4.4 -11, 1.2 -7.8, 0 -3.4 Z"
                    />
                    <circle r="1.7" />
                </g>
            </g>

            <pattern
                :id="idPola"
                :width="ukuranUbin"
                :height="ukuranUbin"
                patternUnits="userSpaceOnUse"
            >
                <use :href="`#${idPola}-satuan`" :transform="`translate(${tengah} ${tengah})`" />

                <!-- Empat sudut. Masing-masing tampil seperempat, dan
                     bertemu dengan ubin tetangganya. -->
                <use :href="`#${idPola}-satuan`" transform="translate(0 0)" />
                <use :href="`#${idPola}-satuan`" :transform="`translate(${ukuranUbin} 0)`" />
                <use :href="`#${idPola}-satuan`" :transform="`translate(0 ${ukuranUbin})`" />
                <use :href="`#${idPola}-satuan`" :transform="`translate(${ukuranUbin} ${ukuranUbin})`" />
            </pattern>
        </defs>

        <rect width="100%" height="100%" :fill="`url(#${idPola})`" />
    </svg>
</template>

<style scoped>
.motif-sulur {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
</style>
