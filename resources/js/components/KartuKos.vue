<script setup>
import { computed, ref } from 'vue';
import TepiCahaya from './TepiCahaya.vue';

const props = defineProps({
    kos: { type: Object, required: true },
    radius: { type: Number, default: 1000 },
    tersorot: { type: Boolean, default: false },

    /**
     * Menandai kos bernilai terbaik — rating tinggi pada harga rendah.
     *
     * Yang dikendalikan hanya lencananya. Tepi bercahaya menyala di semua
     * kartu, sehingga ia tidak lagi bisa dipakai untuk membedakan satu kartu
     * dari yang lain; lencana inilah yang sekarang mengerjakan itu.
     */
    unggulan: { type: Boolean, default: false },
});

defineEmits(['sorot']);

/*
 * Foto yang gagal dimuat diperlakukan sama dengan kos yang memang belum
 * berfoto. Ikon gambar rusak memenuhi kartu jauh lebih buruk daripada bidang
 * berwarna yang memang dirancang untuk itu.
 */
const gagalMuat = ref(false);

const fotoUtama = computed(() =>
    gagalMuat.value ? null : (props.kos.foto?.[0]?.url ?? null),
);

/*
 * Foto masuk dari buram menjadi tajam setelah berkasnya selesai diunduh.
 *
 * Ini mengerjakan sesuatu selain tampilan. Pergantian mendadak dari bidang
 * kosong ke foto membuat kartunya terasa tersentak; penajaman bertahap terasa
 * seperti sesuatu yang memang sedang datang — dan pada koneksi kampus yang
 * lambat, itu selisih yang nyata.
 *
 * Keadaan awalnya buram, bukan tajam. Foto yang sudah ada di singgahan
 * peramban memicu load hampir seketika, sehingga buramnya praktis tidak
 * terlihat; yang lambat mengunduhlah yang mendapat peralihan itu.
 */
const fotoSiap = ref(false);

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
        Kartu bergaya mozaik: foto memenuhi seluruh bidang, keterangannya
        ditumpuk di atas foto itu.

        Keterangan yang dibutuhkan untuk membandingkan kos — nama, harga, dan
        jarak — tetap ada semuanya. Yang berubah hanya tempatnya: dari blok
        teks di bawah foto menjadi lapisan di atasnya. Karena itu di bawah
        teks dipasang gradien gelap; teks putih di atas foto terang tidak
        terbaca, dan foto kamar kos sebagian besar memang terang.

        Yang dilepas dari susunan lama hanya StripJarak. Batang tipis itu
        hilang tertelan foto, dan angkanya sudah disebut pada label jarak.
    -->
    <!--
        Sudut 12px pada bingkai, 10px pada kartunya.

        Selisih dua piksel itu bukan kekeliruan: bingkainya memberi padding
        dua piksel untuk tepi bercahaya, jadi sudut dalam harus lebih kecil
        sebanyak itu. Kalau keduanya disamakan 12px, sudut kartu akan menonjol
        keluar melewati lengkung bingkainya dan tepinya terlihat tebal
        sebelah di tiap pojok.
    -->
    <TepiCahaya class="h-full" jari="12px">
        <article
            v-sorot
            class="group relative h-full overflow-hidden rounded-[10px] bg-[color:var(--color-indigo)] transition duration-200 ease-out"
            :class="tersorot
                ? 'shadow-[0_22px_45px_-18px_rgba(22,35,63,0.55)]'
                : 'shadow-[0_12px_28px_-16px_rgba(22,35,63,0.38)]'"
            @mouseenter="$emit('sorot', kos.id)"
            @focusin="$emit('sorot', kos.id)"
        >
            <RouterLink
                :to="{ name: 'detail-kos', params: { id: kos.id } }"
                class="block h-full"
            >
                <img
                    v-if="fotoUtama"
                    :src="fotoUtama"
                    :alt="`Foto ${kos.nama_kos}`"
                    class="absolute inset-0 size-full object-cover transition-[transform,filter,opacity] duration-700 ease-out group-hover:scale-[1.04] motion-reduce:transition-none"
                    :class="fotoSiap ? 'blur-0 opacity-100' : 'scale-[1.04] opacity-70 blur-lg'"
                    loading="lazy"
                    @load="fotoSiap = true"
                    @error="gagalMuat = true"
                >
                <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center text-sm text-white/45"
                >
                    Belum ada foto
                </div>

                <!-- Gradien pengganjal. Dua perhentian, bukan satu: gradien
                     linear sederhana membuat bagian tengah foto ikut gelap
                     tanpa perlu. -->
                <div
                    class="absolute inset-x-0 bottom-0 h-3/5 bg-gradient-to-t from-[rgba(10,16,30,0.92)] via-[rgba(10,16,30,0.55)] to-transparent"
                    aria-hidden="true"
                />

                <span
                    class="absolute left-3 top-3 z-[2] bg-white/95 px-2.5 py-1 text-xs font-semibold text-[color:var(--color-tinta)]"
                >
                    {{ kos.tipe_kos_label }}
                </span>

                <span
                    v-if="!kos.tersedia"
                    class="absolute right-3 top-3 z-[2] bg-[color:var(--color-maroon)] px-2.5 py-1 text-xs font-semibold text-white"
                >
                    Penuh
                </span>

                <!--
                    Lencana penanda kos bernilai terbaik. Sejak tepi bercahaya
                    dipasang di semua kartu, lencana inilah satu-satunya yang
                    membedakan ketiga kos pilihan dari sisanya.
                -->
                <span
                    v-else-if="unggulan"
                    class="absolute right-3 top-3 z-[2] flex items-center gap-1.5 bg-[color:var(--color-emas)] px-2.5 py-1 text-xs font-semibold text-white"
                >
                    <i class="pi pi-star-fill text-[0.6rem]" aria-hidden="true" />
                    Nilai terbaik
                </span>

                <div class="absolute inset-x-0 bottom-0 z-[2] p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="jarak"
                            class="bg-[color:var(--color-emas)] px-2 py-0.5 text-xs font-semibold text-white"
                        >
                            {{ jarak }} ke kampus
                        </span>
                        <span
                            v-if="kos.jumlah_ulasan"
                            class="text-xs font-semibold text-white/85"
                        >
                            {{ kos.rating_rata }} &#9733; ({{ kos.jumlah_ulasan }})
                        </span>
                    </div>

                    <h3 class="font-display mt-2 text-lg leading-snug font-bold text-white">
                        {{ kos.nama_kos }}
                    </h3>

                    <p class="mt-0.5 line-clamp-1 text-sm text-white/70">
                        {{ kos.alamat }}
                    </p>

                    <div class="mt-2 flex items-end justify-between gap-2">
                        <p class="font-display text-xl leading-none font-extrabold text-white">
                            <span class="text-sm font-semibold text-white/70">Rp</span>
                            {{ harga }}
                            <span class="text-xs font-normal text-white/60">/bln</span>
                        </p>

                        <span
                            v-if="kos.tersedia"
                            class="shrink-0 text-xs text-white/70"
                        >
                            {{ kos.sisa_kamar }} kamar
                        </span>
                    </div>
                </div>
            </RouterLink>
        </article>
    </TepiCahaya>
</template>
