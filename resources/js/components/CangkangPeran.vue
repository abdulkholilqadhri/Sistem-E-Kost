<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { labelArea, menuUntuk } from '../menuPeran';
import { useAuth } from '../stores/auth';
import PilBerkaret from './PilBerkaret.vue';

const auth = useAuth();
const route = useRoute();
const router = useRouter();

/*
 * Butir menu yang rutenya belum terdaftar disaring keluar.
 *
 * Menu di menuPeran.js ditulis utuh mengikuti mockup, sementara halamannya
 * dikerjakan bertahap. Tanpa saringan ini, satu RouterLink yang menunjuk
 * nama rute yang belum ada akan melempar galat dan memadamkan seluruh
 * sidebar — bukan hanya butir itu.
 */
const menu = computed(() => menuUntuk(auth.peran).filter((item) => router.hasRoute(item.nama)));
const area = computed(() => labelArea(auth.peran));

/**
 * Butir sidebar yang sedang ditandai pil.
 *
 * Penanda mengikuti rute, bukan klik terakhir, supaya tetap benar ketika
 * pengguna berpindah lewat tombol di dalam halaman atau menekan tombol Back.
 * Rute yang tidak punya butir sendiri — pembayaran satu pesanan, formulir
 * tambah kos — membuat pil disembunyikan, sama seperti active-class yang
 * juga tidak menyala di sana.
 */
const pilAktif = computed(() => {
    return menu.value.some((item) => item.nama === route.name) ? route.name : null;
});

/**
 * Judul halaman untuk remah-remah di bilah atas.
 *
 * Diambil dari meta.judul rute, bukan dari label menu, karena sebagian
 * halaman tidak punya butir menu sendiri — halaman pembayaran satu pesanan,
 * misalnya, atau formulir tambah kos.
 */
const judul = computed(() => route.meta.judul ?? '');

const laciTerbuka = ref(false);

// Laci di layar kecil menutup sendiri setelah berpindah halaman. Tanpa ini,
// pengguna harus menutupnya manual setiap kali memilih menu.
watch(() => route.fullPath, () => {
    laciTerbuka.value = false;
});

async function keluar() {
    await auth.logout();
    router.push({ name: 'beranda' });
}
</script>

<template>
    <!--
        Cangkang untuk seluruh halaman yang memerlukan login, mengikuti
        mockup SDD hal. 61 dan 62: sidebar tetap di kiri, bilah tipis di
        atas, isi halaman di sisanya.

        Halaman publik — beranda, detail kos, autentikasi — tidak memakai
        cangkang ini. Dokumen yang sama menggambarkannya dengan bilah
        navigasi atas (hal. 59), dan itu yang tetap dipakai di sana.
    -->
    <div class="flex min-h-screen bg-[color:var(--color-kertas)]">
        <!-- Lapisan gelap di belakang laci, hanya layar kecil. -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <button
                v-if="laciTerbuka"
                type="button"
                class="fixed inset-0 z-40 bg-[color:var(--color-tinta)]/45 lg:hidden"
                aria-label="Tutup menu"
                @click="laciTerbuka = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-[color:var(--color-garis)] bg-white transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="laciTerbuka ? 'translate-x-0' : '-translate-x-full'"
        >
            <RouterLink
                :to="{ name: 'beranda' }"
                class="flex items-center gap-2.5 border-b border-[color:var(--color-garis)] px-5 py-4"
            >
                <img src="/logo-mark.png" width="32" height="32" alt="" class="size-8">
                <span class="font-display text-lg font-extrabold tracking-tight">E&#8209;Kost</span>
                <span
                    class="ml-auto rounded bg-[color:var(--color-kertas)] px-2 py-0.5 text-[0.65rem] font-semibold tracking-wide text-[color:var(--color-tinta)]/60 uppercase"
                >
                    {{ area }}
                </span>
            </RouterLink>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <p class="px-2 pb-2 text-[0.68rem] font-semibold tracking-wider text-[color:var(--color-tinta)]/45 uppercase">
                    Navigasi Akun
                </p>

                <!--
                    Latar butir aktif digambar oleh pil yang meluncur di
                    belakangnya, bukan oleh active-class.

                    Pilnya sengaja terang, bukan indigo. Warna teks butir
                    berganti seketika, sementara pil masih menempuh ~320 ms
                    perjalanannya. Dengan pil gelap dan teks putih, butir
                    tujuan sudah berteks putih di atas latar putih sebelum
                    pilnya tiba — tak terbaca selama perjalanan itu. Teks
                    gelap terbaca di atas pil maupun tanpa pil.

                    Huruf tebal dipertahankan. Di sidebar setiap baris selebar
                    penuh, jadi menebalkan huruf tidak menggeser posisi butir
                    lain seperti yang terjadi pada deretan mendatar.

                    Warna teks aktif diberi tanda ! karena kelas dasarnya juga
                    mengatur warna teks (tinta/75), dan dalam CSS hasil Tailwind
                    yang /75 tersusun belakangan sehingga selalu menang.

                    Latar saat disentuh kursor dibuang karena warnanya kini
                    sama dengan warna pil, sehingga butir yang disentuh kursor
                    tak bisa dibedakan dari butir yang sedang terbuka.
                -->
                <PilBerkaret :aktif="pilAktif" arah="tegak">
                    <RouterLink
                        v-for="item in menu"
                        :key="item.nama"
                        :to="{ name: item.nama }"
                        :data-pil="item.nama"
                        class="relative mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-[color:var(--color-tinta)]/75 transition hover:text-[color:var(--color-tinta)]"
                        active-class="font-semibold text-[color:var(--color-tinta)]!"
                    >
                        <i :class="`pi ${item.ikon} text-sm`" aria-hidden="true" />
                        {{ item.label }}
                    </RouterLink>
                </PilBerkaret>
            </nav>

            <!-- Keluar duduk di kaki sidebar, terpisah dari menu navigasi,
                 persis seperti pada mockup. Warnanya merah supaya tidak
                 tertukar dengan butir menu biasa. -->
            <div class="border-t border-[color:var(--color-garis)] p-3">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-[color:var(--color-maroon)] transition hover:bg-[color:var(--color-maroon)]/8"
                    @click="keluar"
                >
                    <i class="pi pi-sign-out text-sm" aria-hidden="true" />
                    Keluar
                </button>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-30 flex items-center gap-3 border-b border-[color:var(--color-garis)] bg-white/95 px-4 py-3 backdrop-blur sm:px-6"
            >
                <button
                    type="button"
                    class="-ml-1 rounded-lg p-2 text-[color:var(--color-tinta)]/70 transition hover:bg-[color:var(--color-kertas)] lg:hidden"
                    :aria-expanded="laciTerbuka"
                    aria-label="Buka menu"
                    @click="laciTerbuka = true"
                >
                    <i class="pi pi-bars" aria-hidden="true" />
                </button>

                <!-- Remah-remah: "Penyewa › Pembayaran", seperti mockup. -->
                <nav class="min-w-0 text-sm" aria-label="Remah-remah">
                    <span class="text-[color:var(--color-tinta)]/50">{{ area }}</span>
                    <template v-if="judul">
                        <i class="pi pi-angle-right mx-1 text-[0.6rem] text-[color:var(--color-tinta)]/35" aria-hidden="true" />
                        <span class="font-semibold">{{ judul }}</span>
                    </template>
                </nav>

                <div class="ml-auto flex min-w-0 items-center gap-3">
                    <div class="min-w-0 text-right">
                        <p class="truncate text-sm font-semibold">{{ auth.pengguna?.nama }}</p>
                        <p class="truncate text-xs text-[color:var(--color-tinta)]/55">
                            {{ auth.pengguna?.role_label }}
                        </p>
                    </div>

                    <!-- Inisial, bukan foto: sistem ini tidak menyimpan foto
                         profil, dan lingkaran abu-abu kosong terlihat seperti
                         gambar yang gagal dimuat. -->
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-indigo)] text-sm font-semibold text-white"
                        aria-hidden="true"
                    >
                        {{ (auth.pengguna?.nama ?? '?').charAt(0).toUpperCase() }}
                    </span>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <RouterView v-slot="{ Component, route: anak }">
                    <Transition name="halaman" mode="out-in">
                        <component :is="Component" :key="anak.path" />
                    </Transition>
                </RouterView>
            </main>
        </div>
    </div>
</template>
