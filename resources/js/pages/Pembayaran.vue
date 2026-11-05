<script setup>
import { computed, onMounted, ref } from 'vue';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import api, { pesanGalat } from '../api';

const toast = useToast();

const daftar = ref([]);
const memuat = ref(true);

/**
 * Hanya pesanan yang berurusan dengan pembayaran yang ditampilkan.
 *
 * Halaman Pemesanan Saya sudah memuat seluruh riwayat; mengulang daftar yang
 * sama di sini hanya membuat dua menu yang terasa serupa dan tidak jelas
 * bedanya. Yang membedakan halaman ini adalah penyempitannya.
 */
const perluPerhatian = computed(() =>
    daftar.value.filter((satu) =>
        ['menunggu_pembayaran', 'menunggu_verifikasi'].includes(satu.status),
    ),
);

const sudahSelesai = computed(() =>
    daftar.value.filter((satu) => ['aktif', 'selesai'].includes(satu.status) && satu.pembayaran),
);

async function muat() {
    memuat.value = true;

    try {
        const { data } = await api.get('/pemesanan');
        daftar.value = data.data;
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Daftar pembayaran gagal dimuat',
            detail: pesanGalat(error),
            life: 8000,
        });
    } finally {
        memuat.value = false;
    }
}

function rupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(angka ?? 0);
}

function tenggat(iso) {
    return iso
        ? new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
        : null;
}

onMounted(muat);
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <header v-muncul:naik class="mb-5">
            <h1 class="font-display text-3xl font-extrabold tracking-tight">Pembayaran</h1>
            <p class="mt-1 text-sm text-[color:var(--color-tinta)]/65">
                Pesanan yang menunggu transfer atau sedang diperiksa pemilik kos.
            </p>
        </header>

        <div v-if="memuat" class="space-y-3">
            <Skeleton v-for="n in 3" :key="n" height="6rem" border-radius="0.75rem" />
        </div>

        <template v-else>
            <p
                v-if="perluPerhatian.length === 0"
                v-muncul:mekar
                class="rounded-xl border border-[color:var(--color-garis)] bg-white p-8 text-center text-sm text-[color:var(--color-tinta)]/60"
            >
                Tidak ada pembayaran yang menunggu.
                <RouterLink :to="{ name: 'beranda' }" class="font-semibold underline">Cari kos</RouterLink>
            </p>

            <ul v-else class="space-y-3">
                <li
                    v-for="(pesanan, urutan) in perluPerhatian"
                    :key="pesanan.id"
                    v-muncul="{ nama: 'naik', jeda: Math.min(urutan, 7) * 70 }"
                >
                    <RouterLink
                        :to="{ name: 'pembayaran-detail', params: { id: pesanan.id } }"
                        class="group flex items-center gap-4 rounded-xl border border-[color:var(--color-garis)] bg-white p-4 transition hover:border-[color:var(--color-indigo)]"
                    >
                        <img
                            v-if="pesanan.kos?.foto?.[0]?.url"
                            :src="pesanan.kos.foto[0].url"
                            :alt="pesanan.kos.nama_kos"
                            class="size-16 shrink-0 rounded-lg object-cover"
                        >

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ pesanan.kos?.nama_kos }}</p>
                                <Tag
                                    :value="pesanan.status_label"
                                    :severity="pesanan.status === 'menunggu_pembayaran' ? 'warn' : 'info'"
                                />
                            </div>

                            <p class="mt-1 font-display text-lg font-extrabold">
                                {{ rupiah(pesanan.total_harga) }}
                            </p>

                            <p
                                v-if="pesanan.status === 'menunggu_pembayaran' && tenggat(pesanan.batas_pembayaran)"
                                class="mt-0.5 text-xs text-[color:var(--color-maroon)]"
                            >
                                Batas transfer {{ tenggat(pesanan.batas_pembayaran) }}
                            </p>
                        </div>

                        <i
                            class="pi pi-arrow-right shrink-0 text-xs text-[color:var(--color-indigo)] transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </RouterLink>
                </li>
            </ul>

            <section v-if="sudahSelesai.length" class="mt-8">
                <h2 v-muncul:kiri class="font-display text-lg font-semibold">Sudah terverifikasi</h2>

                <ul class="mt-3 space-y-2">
                    <li
                        v-for="(pesanan, urutan) in sudahSelesai"
                        :key="pesanan.id"
                        v-muncul="{ nama: 'kiri', jeda: Math.min(urutan, 5) * 60 }"
                        class="flex items-center justify-between gap-3 rounded-lg border border-[color:var(--color-garis)] bg-white px-4 py-3 text-sm"
                    >
                        <span class="min-w-0 truncate">{{ pesanan.kos?.nama_kos }}</span>
                        <span class="shrink-0 font-semibold">{{ rupiah(pesanan.pembayaran?.jumlah_bayar) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
