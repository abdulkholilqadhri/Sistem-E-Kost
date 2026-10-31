<script setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Rating from 'primevue/rating';
import Skeleton from 'primevue/skeleton';
import api, { pesanGalat } from '../api';

const props = defineProps({
    kosId: { type: [Number, String], required: true },
    ratingRata: { type: Number, default: null },
    jumlahUlasan: { type: Number, default: 0 },
});

const ulasan = ref([]);
const memuat = ref(true);
const galat = ref(null);
const halaman = ref(1);
const halamanTerakhir = ref(1);

async function muat(halamanBerikutnya = 1) {
    memuat.value = true;
    galat.value = null;

    try {
        const { data } = await api.get(`/kos/${props.kosId}/ulasan`, {
            params: { page: halamanBerikutnya },
        });

        // Halaman berikutnya ditambahkan, bukan menggantikan — supaya ulasan
        // yang sudah dibaca tidak hilang saat pengguna menekan "muat lagi".
        ulasan.value = halamanBerikutnya === 1
            ? data.data
            : [...ulasan.value, ...data.data];

        halaman.value = data.meta.current_page;
        halamanTerakhir.value = data.meta.last_page;
    } catch (error) {
        galat.value = pesanGalat(error, 'Ulasan tidak dapat dimuat.');
    } finally {
        memuat.value = false;
    }
}

function tanggal(iso) {
    return iso
        ? new Date(iso).toLocaleDateString('id-ID', { dateStyle: 'long' })
        : '';
}

/**
 * Nama penulis dipersingkat jadi huruf awal tiap kata, untuk lingkaran
 * avatar. Tidak memakai gambar: tidak ada foto profil di sistem ini, dan
 * avatar yang dibangkitkan dari layanan luar akan mengirim data pengguna
 * ke pihak ketiga tanpa alasan.
 */
function inisial(nama) {
    return (nama ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((kata) => kata.charAt(0).toUpperCase())
        .join('');
}

onMounted(() => muat());
</script>

<template>
    <section>
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2 class="font-display text-xl font-semibold">Ulasan penyewa</h2>

            <div v-if="jumlahUlasan" class="flex items-center gap-2">
                <Rating :model-value="Math.round(ratingRata)" readonly />
                <span class="text-sm text-[color:var(--color-tinta)]/70">
                    {{ ratingRata }} dari {{ jumlahUlasan }} ulasan
                </span>
            </div>
        </div>

        <div v-if="memuat && ulasan.length === 0" class="mt-4 space-y-4">
            <div v-for="n in 2" :key="n" class="flex gap-3">
                <Skeleton shape="circle" size="2.5rem" />
                <div class="flex-1 space-y-2">
                    <Skeleton width="30%" height="0.9rem" />
                    <Skeleton width="100%" height="0.8rem" />
                    <Skeleton width="80%" height="0.8rem" />
                </div>
            </div>
        </div>

        <p v-else-if="galat" class="mt-4 text-sm text-[color:var(--color-maroon)]">
            {{ galat }}
        </p>

        <!--
            Keadaan kosong menjelaskan sebabnya, bukan sekadar menyatakan
            tidak ada. Ulasan hanya bisa ditulis penyewa yang masa sewanya
            sudah berakhir, jadi kos baru memang wajar belum punya ulasan.
        -->
        <p
            v-else-if="ulasan.length === 0"
            class="mt-4 bg-white p-5 text-sm text-[color:var(--color-tinta)]/65 shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)]"
        >
            Belum ada ulasan untuk kos ini. Ulasan hanya dapat ditulis penyewa
            yang masa sewanya sudah berakhir.
        </p>

        <ul v-else class="mt-4 space-y-4">
            <li
                v-for="item in ulasan"
                :key="item.id"
                class="flex gap-3 bg-white p-4 shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)]"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[color:var(--color-kertas)] text-sm font-semibold text-[color:var(--color-indigo)]"
                    aria-hidden="true"
                >
                    {{ inisial(item.penulis?.nama) }}
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-medium">{{ item.penulis?.nama ?? 'Penyewa' }}</span>
                        <Rating :model-value="item.rating" readonly />
                        <span class="text-xs text-[color:var(--color-tinta)]/50">
                            {{ tanggal(item.ditulis_pada) }}
                        </span>
                    </div>

                    <p v-if="item.komentar" class="mt-1.5 text-sm leading-relaxed text-[color:var(--color-tinta)]/85">
                        {{ item.komentar }}
                    </p>
                </div>
            </li>
        </ul>

        <div v-if="halaman < halamanTerakhir" class="mt-4">
            <Button
                label="Muat ulasan lainnya"
                severity="secondary"
                outlined
                :loading="memuat"
                @click="muat(halaman + 1)"
            />
        </div>
    </section>
</template>
