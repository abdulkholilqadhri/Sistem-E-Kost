<script setup>
import { computed } from 'vue';
import StripJarak from './StripJarak.vue';

const props = defineProps({
    kos: { type: Object, required: true },
    radius: { type: Number, default: 1000 },
    tersorot: { type: Boolean, default: false },
});

defineEmits(['sorot']);

const fotoUtama = computed(() => props.kos.foto?.[0]?.url ?? null);

const harga = computed(() =>
    new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(props.kos.harga_per_bulan ?? 0),
);

const jarak = computed(() => {
    if (props.kos.jarak_meter == null) {
        return null;
    }

    return props.kos.jarak_meter < 1000
        ? `${props.kos.jarak_meter} m`
        : `${(props.kos.jarak_meter / 1000).toFixed(1)} km`;
});
</script>

<template>
    <!--
        Bentuk kartu mengikuti referensi: sudut siku, tanpa garis tepi, dan
        diangkat dengan bayangan lebar yang lembut. Yang memisahkan kartu dari
        latar adalah bayangannya, bukan garis — itu sebabnya kartunya terasa
        mengambang, bukan menempel.

        Saat disorot, kartunya naik sedikit. Gerakan ini menjawab tindakan
        pengguna dan sekaligus menandai kartu mana yang penandanya sedang
        menyala di peta.
    -->
    <article
        class="group overflow-hidden bg-white transition duration-200 ease-out"
        :class="tersorot
            ? '-translate-y-1 shadow-[0_22px_45px_-18px_rgba(22,35,63,0.55)]'
            : 'shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)] hover:-translate-y-1 hover:shadow-[0_22px_45px_-18px_rgba(22,35,63,0.5)]'"
        @mouseenter="$emit('sorot', kos.id)"
        @focusin="$emit('sorot', kos.id)"
    >
        <RouterLink :to="{ name: 'detail-kos', params: { id: kos.id } }" class="block">
            <div class="relative aspect-[4/3] overflow-hidden bg-[color:var(--color-kertas)]">
                <img
                    v-if="fotoUtama"
                    :src="fotoUtama"
                    :alt="`Foto ${kos.nama_kos}`"
                    class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.05]"
                    loading="lazy"
                >
                <div v-else class="flex size-full items-center justify-center text-sm text-[color:var(--color-tinta)]/40">
                    Belum ada foto
                </div>

                <!-- Jarak diletakkan di atas foto, bukan di dalam blok teks.
                     Ini angka yang paling menentukan keputusan; menaruhnya di
                     antara nama dan harga membuatnya ikut tenggelam. -->
                <span
                    v-if="jarak"
                    class="bendera-jarak absolute bottom-3 left-3 px-3 py-1.5 text-base"
                >
                    {{ jarak }} ke kampus
                </span>

                <span class="absolute left-3 top-3 bg-white/95 px-2.5 py-1 text-xs font-semibold">
                    {{ kos.tipe_kos_label }}
                </span>

                <span
                    v-if="!kos.tersedia"
                    class="absolute right-3 top-3 bg-[color:var(--color-maroon)] px-2.5 py-1 text-xs font-semibold text-white"
                >
                    Penuh
                </span>
            </div>

            <div class="p-4">
                <h3 class="font-display text-lg leading-snug font-semibold">
                    {{ kos.nama_kos }}
                </h3>

                <p class="mt-1 line-clamp-1 text-sm text-[color:var(--color-tinta)]/55">
                    {{ kos.alamat }}
                </p>

                <StripJarak
                    :jarak-meter="kos.jarak_meter"
                    :radius-meter="radius"
                    class="mt-3"
                />

                <div class="mt-3 flex items-end justify-between gap-2">
                    <p class="font-display text-2xl leading-none font-extrabold">
                        <span class="text-base font-semibold text-[color:var(--color-tinta)]/60">Rp</span>
                        {{ harga }}
                        <span class="text-sm font-normal text-[color:var(--color-tinta)]/50">/bln</span>
                    </p>

                    <span
                        v-if="kos.jumlah_ulasan"
                        class="shrink-0 text-sm text-[color:var(--color-tinta)]/60"
                    >
                        {{ kos.rating_rata }} &#9733;
                    </span>
                    <span
                        v-else-if="kos.tersedia"
                        class="shrink-0 text-sm text-[color:var(--color-tinta)]/60"
                    >
                        {{ kos.sisa_kamar }} kamar
                    </span>
                </div>

                <!-- Tautan teks di kaki kartu, seperti pada referensi.
                     Seluruh kartu sudah dapat diklik; ini menegaskan ke mana
                     kliknya menuju. -->
                <p
                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[color:var(--color-indigo)] transition-transform duration-200 group-hover:translate-x-0.5"
                >
                    Lihat detail
                    <i class="pi pi-arrow-right text-[0.7rem]" aria-hidden="true" />
                </p>
            </div>
        </RouterLink>
    </article>
</template>
