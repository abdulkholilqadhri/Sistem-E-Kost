<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuth } from '../stores/auth';

const auth = useAuth();
const router = useRouter();
const menuTerbuka = ref(false);

/**
 * Menu disusun menurut peran. Penyewa, pemilik kos, dan admin mengerjakan hal
 * yang berbeda, sehingga tidak masuk akal memberi mereka menu yang sama.
 *
 * Menyembunyikan menu bukan pengamanan: siapa pun bisa mengetik alamatnya
 * langsung. Yang menolak adalah middleware role dan Policy di server.
 */
const menu = computed(() => {
    if (auth.adalahAdmin) {
        return [
            { nama: 'admin-pengajuan', label: 'Verifikasi' },
            { nama: 'beranda', label: 'Peta Kos' },
        ];
    }

    if (auth.adalahPemilik) {
        return [
            { nama: 'pemilik-kos', label: 'Kos Saya' },
            { nama: 'pemilik-pesanan', label: 'Pesanan Masuk' },
            { nama: 'beranda', label: 'Peta Kos' },
        ];
    }

    if (auth.masuk) {
        return [
            { nama: 'beranda', label: 'Cari Kos' },
            { nama: 'pesanan-saya', label: 'Pesanan Saya' },
            { nama: 'jadi-pemilik', label: 'Jadi Pemilik' },
        ];
    }

    return [{ nama: 'beranda', label: 'Cari Kos' }];
});

async function keluar() {
    await auth.logout();
    menuTerbuka.value = false;
    router.push({ name: 'beranda' });
}
</script>

<template>
    <!--
        Bilah navigasi melayang di atas isi halaman, bukan mendorongnya ke
        bawah. Posisinya fixed supaya pita bergradien di beranda dapat
        terlihat di belakangnya — itu yang memberi kesan "pulau" pada
        referensi desainnya.

        Konsekuensinya, isi halaman perlu ruang di atas. Ruang itu diberikan
        sekali di App.vue, dan hanya pita beranda yang sengaja menariknya
        kembali agar gradiennya menembus ke belakang bilah ini.
    -->
    <header class="fixed inset-x-0 top-0 z-50 px-3 pt-3 sm:px-4 sm:pt-4">
        <nav
            class="mx-auto max-w-6xl rounded-2xl bg-white/95 px-4 py-2.5 shadow-[0_10px_30px_-12px_rgba(22,35,63,0.35)] backdrop-blur sm:px-5"
        >
            <div class="grid grid-cols-[1fr_auto] items-center gap-3 lg:grid-cols-[1fr_auto_1fr]">
                <!-- Tautan menu, kiri. Disembunyikan di layar kecil. -->
                <div class="hidden items-center gap-1 lg:flex">
                    <RouterLink
                        v-for="item in menu"
                        :key="item.nama"
                        :to="{ name: item.nama }"
                        class="rounded-lg px-3 py-1.5 text-sm text-[color:var(--color-tinta)]/75 transition hover:bg-[color:var(--color-kertas)] hover:text-[color:var(--color-tinta)]"
                        active-class="bg-[color:var(--color-kertas)] font-semibold text-[color:var(--color-tinta)]"
                    >
                        {{ item.label }}
                    </RouterLink>
                </div>

                <!--
                    Logo di tengah pada layar lebar, di kiri pada layar kecil.
                    Di ponsel, logo di tengah akan bertabrakan dengan tombol
                    menu dan membuat keduanya sempit.
                -->
                <RouterLink
                    :to="{ name: 'beranda' }"
                    class="font-display text-lg font-extrabold tracking-tight lg:justify-self-center"
                >
                    E&#8209;Kost
                </RouterLink>

                <!-- Tindakan, kanan. -->
                <div class="hidden items-center justify-end gap-2 lg:flex">
                    <template v-if="auth.masuk">
                        <span class="px-2 text-sm text-[color:var(--color-tinta)]/60">
                            {{ auth.pengguna.nama }}
                        </span>
                        <button
                            type="button"
                            class="rounded-full border border-[color:var(--color-garis)] px-4 py-1.5 text-sm transition hover:bg-[color:var(--color-kertas)]"
                            @click="keluar"
                        >
                            Keluar
                        </button>
                    </template>

                    <template v-else>
                        <RouterLink
                            :to="{ name: 'masuk' }"
                            class="rounded-lg px-3 py-1.5 text-sm text-[color:var(--color-tinta)]/75 transition hover:text-[color:var(--color-tinta)]"
                        >
                            Masuk
                        </RouterLink>
                        <RouterLink
                            :to="{ name: 'daftar' }"
                            class="group inline-flex items-center gap-2 rounded-full bg-[color:var(--color-indigo)] py-1.5 pr-1.5 pl-4 text-sm font-semibold text-white transition hover:bg-[color:var(--color-indigo-muda)]"
                        >
                            Daftar
                            <span
                                class="flex size-6 items-center justify-center rounded-full bg-white/20 transition-transform duration-200 group-hover:translate-x-0.5"
                            >
                                <i class="pi pi-arrow-right text-[0.65rem]" aria-hidden="true" />
                            </span>
                        </RouterLink>
                    </template>
                </div>

                <!-- Tombol menu, hanya layar kecil. -->
                <button
                    class="justify-self-end rounded-lg p-2 text-[color:var(--color-tinta)]/70 transition hover:bg-[color:var(--color-kertas)] lg:hidden"
                    type="button"
                    :aria-expanded="menuTerbuka"
                    aria-label="Buka menu"
                    @click="menuTerbuka = !menuTerbuka"
                >
                    <i :class="menuTerbuka ? 'pi pi-times' : 'pi pi-bars'" aria-hidden="true" />
                </button>
            </div>

            <!-- Panel menu layar kecil, terbuka di dalam pulau yang sama. -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                leave-active-class="transition duration-150 ease-in"
                enter-from-class="opacity-0 -translate-y-1"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div v-if="menuTerbuka" class="mt-3 border-t border-[color:var(--color-garis)] pt-3 lg:hidden">
                    <RouterLink
                        v-for="item in menu"
                        :key="item.nama"
                        :to="{ name: item.nama }"
                        class="block rounded-lg px-3 py-2 text-sm text-[color:var(--color-tinta)]/80 transition hover:bg-[color:var(--color-kertas)]"
                        active-class="bg-[color:var(--color-kertas)] font-semibold"
                        @click="menuTerbuka = false"
                    >
                        {{ item.label }}
                    </RouterLink>

                    <div class="mt-2 border-t border-[color:var(--color-garis)] pt-2">
                        <template v-if="auth.masuk">
                            <p class="px-3 py-1 text-xs text-[color:var(--color-tinta)]/50">
                                {{ auth.pengguna.nama }} &middot; {{ auth.pengguna.role_label }}
                            </p>
                            <button
                                type="button"
                                class="block w-full rounded-lg px-3 py-2 text-left text-sm transition hover:bg-[color:var(--color-kertas)]"
                                @click="keluar"
                            >
                                Keluar
                            </button>
                        </template>

                        <template v-else>
                            <RouterLink
                                :to="{ name: 'masuk' }"
                                class="block rounded-lg px-3 py-2 text-sm transition hover:bg-[color:var(--color-kertas)]"
                                @click="menuTerbuka = false"
                            >
                                Masuk
                            </RouterLink>
                            <RouterLink
                                :to="{ name: 'daftar' }"
                                class="mt-1 block rounded-full bg-[color:var(--color-indigo)] px-4 py-2 text-center text-sm font-semibold text-white"
                                @click="menuTerbuka = false"
                            >
                                Daftar
                            </RouterLink>
                        </template>
                    </div>
                </div>
            </Transition>
        </nav>
    </header>
</template>
