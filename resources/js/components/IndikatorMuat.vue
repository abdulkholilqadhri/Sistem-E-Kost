<script setup>
import { useIndikatorMuat } from '../indikatorMuat';

const { aktif, kemajuan } = useIndikatorMuat();
</script>

<template>
    <!--
        Batang tipis di tepi atas layar. Diletakkan di atas segalanya,
        termasuk bilah navigasi yang menempel.

        aria-hidden dipasang karena batang ini semata isyarat visual;
        pembaca layar diberi tahu lewat teks di bawahnya, yang berubah
        saat pemuatan dimulai dan selesai.
    -->
    <Transition
        enter-active-class="transition-opacity duration-150"
        leave-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-to-class="opacity-0"
    >
        <div v-if="aktif" class="pita-muat" aria-hidden="true">
            <span class="pita-muat__isi" :style="{ width: `${kemajuan}%` }" />
        </div>
    </Transition>

    <span class="sr-only" role="status" aria-live="polite">
        {{ aktif ? 'Memuat halaman' : '' }}
    </span>
</template>
