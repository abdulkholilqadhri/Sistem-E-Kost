<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Toast from 'primevue/toast';
import IndikatorMuat from './components/IndikatorMuat.vue';
import NavBar from './components/NavBar.vue';
import PitaUlasan from './components/PitaUlasan.vue';
import { useAuth } from './stores/auth';

const auth = useAuth();
const rute = useRoute();

/**
 * Dua tata letak, dipilih oleh rutenya sendiri.
 *
 * Halaman publik memakai bilah navigasi atas; halaman yang memerlukan login
 * memakai cangkang sidebar (CangkangPeran.vue). Pembagian ini mengikuti SDD
 * BAB IV, yang memang menggambarkan keduanya — bilah atas pada mockup
 * pencarian (hal. 59), sidebar pada mockup pembayaran dan dasbor admin
 * (hal. 61 dan 62).
 *
 * Cangkang membawa bilah atas, navigasi, dan kakinya sendiri, jadi ketiganya
 * dilepas di sini supaya tidak tertumpuk.
 */
const pakaiCangkang = computed(() => rute.meta.cangkang === true);

/**
 * Pita ulasan hanya tampil di halaman publik yang memang untuk dijelajahi.
 *
 * Dua tempat sengaja dikecualikan. Halaman masuk dan daftar punya tata
 * letaknya sendiri setinggi layar, dan pita di bawahnya hanya menambah gulir
 * pada halaman yang seharusnya selesai dalam satu layar. Halaman 404 juga
 * dilewati: orang yang tersesat sedang mencari jalan keluar, bukan sedang
 * membaca ulasan.
 */
const tampilkanPita = computed(
    () => ! pakaiCangkang.value
        && rute.meta.tamuSaja !== true
        && rute.name !== 'tidak-ditemukan',
);

onMounted(() => auth.periksaSesi());
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <IndikatorMuat />

        <NavBar v-if="!pakaiCangkang" />

        <!--
            Ruang untuk bilah navigasi yang melayang.
            Bilahnya berposisi fixed, jadi ia tidak lagi memakan tinggi
            sendiri. Ruang itu diberikan sekali di sini, bukan di setiap
            halaman — supaya halaman baru tidak pernah lupa menyediakannya
            dan isinya tertutup bilah.

            Halaman bercangkang tidak membutuhkannya: bilah atasnya ikut
            mengalir bersama isi, bukan melayang di atasnya.
        -->
        <main class="flex-1" :class="! pakaiCangkang && 'pt-20 sm:pt-24'">
            <RouterView v-slot="{ Component, route }">
                <!--
                    Satu transisi singkat yang menjawab perpindahan halaman.
                    Bukan animasi masuk di setiap bagian: gerakan yang tidak
                    dipicu pengguna hanya membuat antarmuka terasa lambat.
                    Durasinya dinolkan pada perangkat yang meminta pengurangan
                    gerak — diatur di app.css.
                -->
                <!--
                    Kunci memakai route.meta.kunci bila ada, supaya beberapa
                    rute dapat berbagi satu komponen tanpa dibongkar-pasang
                    ulang saat berpindah di antaranya. Dipakai halaman masuk
                    dan daftar, yang animasi panel gesernya akan hilang bila
                    komponennya dipasang ulang.
                -->
                <Transition name="halaman" mode="out-in">
                    <component :is="Component" :key="route.meta.kunci ?? route.path" />
                </Transition>
            </RouterView>
        </main>

        <PitaUlasan v-if="tampilkanPita" />

        <footer
            v-if="!pakaiCangkang"
            class="border-t border-[color:var(--color-garis)] bg-white px-4 py-6 text-center text-sm text-[color:var(--color-tinta)]/60"
        >
            <p>E-Kost &mdash; pencarian kos berbasis lokasi di sekitar Universitas Sulawesi Barat</p>
            <p class="mt-1">
                Data peta oleh
                <a
                    href="https://www.openstreetmap.org/copyright"
                    class="underline hover:text-[color:var(--color-indigo)]"
                    target="_blank"
                    rel="noopener"
                >kontributor OpenStreetMap</a>
            </p>
        </footer>

        <Toast position="bottom-right" />
    </div>
</template>
