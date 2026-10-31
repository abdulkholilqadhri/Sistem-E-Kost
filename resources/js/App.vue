<script setup>
import { onMounted } from 'vue';
import Toast from 'primevue/toast';
import IndikatorMuat from './components/IndikatorMuat.vue';
import NavBar from './components/NavBar.vue';
import { useAuth } from './stores/auth';

const auth = useAuth();

onMounted(() => auth.periksaSesi());
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <IndikatorMuat />

        <NavBar />

        <!--
            Ruang untuk bilah navigasi yang melayang.
            Bilahnya berposisi fixed, jadi ia tidak lagi memakan tinggi
            sendiri. Ruang itu diberikan sekali di sini, bukan di setiap
            halaman — supaya halaman baru tidak pernah lupa menyediakannya
            dan isinya tertutup bilah.
        -->
        <main class="flex-1 pt-20 sm:pt-24">
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

        <footer class="border-t border-[color:var(--color-garis)] bg-white px-4 py-6 text-center text-sm text-[color:var(--color-tinta)]/60">
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
