<script setup>
import { computed } from 'vue';

/**
 * Menunjukkan seberapa besar bagian radius pencarian yang dimakan oleh jarak
 * sebuah kos. Batang pendek berarti dekat, batang panjang berarti di tepi
 * radius.
 *
 * Gunanya membandingkan sekilas: mata menangkap panjang batang jauh lebih
 * cepat daripada membaca deretan angka meter satu per satu.
 *
 * Dibuat sendiri, bukan memakai ProgressBar PrimeVue, karena maknanya bukan
 * kemajuan menuju sesuatu melainkan letak pada sebuah rentang. Komponen
 * progres akan menyarankan arti yang salah.
 */
const props = defineProps({
    jarakMeter: { type: Number, default: null },
    radiusMeter: { type: Number, required: true },
});

const persen = computed(() => {
    if (props.jarakMeter == null || props.radiusMeter <= 0) {
        return null;
    }

    return Math.min(100, Math.max(3, (props.jarakMeter / props.radiusMeter) * 100));
});

const deskripsi = computed(
    () => `${props.jarakMeter} meter dari titik acuan, dalam radius ${props.radiusMeter} meter`,
);
</script>

<template>
    <div v-if="persen !== null" class="strip-jarak" :aria-label="deskripsi" role="img">
        <span class="strip-jarak__isi" :style="{ width: `${persen}%` }" />
    </div>
</template>
