<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../api';

/**
 * Pita ulasan yang berjalan mendatar di atas kaki halaman.
 *
 * Hiasan yang membawa isi sungguhan: ulasan di sini benar-benar ditulis
 * penyewa yang sudah menyelesaikan sewanya, bukan kalimat yang dikarang untuk
 * mengisi ruang.
 *
 * KEGAGALANNYA DIAM
 *
 * Bila permintaannya gagal atau belum ada satu pun ulasan, komponen ini tidak
 * menampilkan apa pun — bukan pesan galat, bukan rangka tiruan. Ini bagian
 * paling bawah halaman dan tidak ada yang datang ke sini untuk membacanya;
 * pesan "gagal memuat ulasan" di kaki beranda hanya membuat seluruh situs
 * terasa rusak padahal pencariannya baik-baik saja.
 */
const daftar = ref([]);

/*
 * Dipakai dua kali berurutan supaya sambungannya tidak pernah terlihat.
 *
 * Animasinya menggeser rel sejauh tepat setengah lebarnya. Pada titik itu
 * salinan kedua berada persis di posisi awal salinan pertama, sehingga
 * lompatan kembali ke nol tidak terlihat sama sekali.
 */
const rel = computed(() => [...daftar.value, ...daftar.value]);

/*
 * Durasi mengikuti jumlah ulasan, bukan angka tetap.
 *
 * Dengan durasi tetap, tiga ulasan akan merayap dan dua belas akan melesat.
 * Enam detik per ulasan membuat kecepatannya sama berapa pun isinya.
 */
const durasi = computed(() => `${Math.max(daftar.value.length, 1) * 6}s`);

async function muat() {
    try {
        const { data } = await api.get('/ulasan/terbaru');

        // Di bawah tiga ulasan, pita yang berjalan lebih terlihat seperti
        // kesalahan daripada hiasan — isinya habis sebelum satu putaran.
        daftar.value = data.data.length >= 3 ? data.data : [];
    } catch {
        daftar.value = [];
    }
}

onMounted(muat);
</script>

<template>
    <section v-if="daftar.length" class="pita-ulasan" aria-labelledby="judul-pita-ulasan">
        <div class="mx-auto max-w-[1800px] px-4 sm:px-5 lg:px-6">
            <!-- Judulnya memakai bintang, lambang yang sama dengan yang dipakai
                 tiap kartu di bawahnya. Ikonnya disembunyikan dari pembaca
                 layar: artinya sudah termuat dalam teks judulnya sendiri. -->
            <h2
                id="judul-pita-ulasan"
                class="font-display mb-3 flex items-center gap-2 text-lg font-semibold"
            >
                <i class="pi pi-star-fill text-sm text-[color:var(--color-emas)]" aria-hidden="true" />
                Rating dari penyewa dan ulasan
            </h2>
        </div>

        <div class="jendela">
            <div class="rel" :style="{ animationDuration: durasi }">
                <!--
                    Salinan kedua disembunyikan dari pembaca layar. Keduanya
                    berisi teks yang sama persis, dan tanpa ini setiap ulasan
                    akan dibacakan dua kali.
                -->
                <RouterLink
                    v-for="(ulasan, urutan) in rel"
                    :key="`${ulasan.id}-${urutan}`"
                    :to="{ name: 'detail-kos', params: { id: ulasan.kos?.id } }"
                    class="kartu-ulasan"
                    :aria-hidden="urutan >= daftar.length ? 'true' : undefined"
                    :tabindex="urutan >= daftar.length ? -1 : undefined"
                >
                    <span class="bintang" :aria-label="`${ulasan.rating} dari 5`">
                        <i
                            v-for="n in 5"
                            :key="n"
                            class="pi"
                            :class="n <= ulasan.rating ? 'pi-star-fill' : 'pi-star'"
                            aria-hidden="true"
                        />
                    </span>

                    <p class="komentar">{{ ulasan.komentar }}</p>

                    <span class="penulis">
                        {{ ulasan.penulis?.nama }} &middot; {{ ulasan.kos?.nama_kos }}
                    </span>
                </RouterLink>
            </div>
        </div>
    </section>
</template>

<style scoped>
.pita-ulasan {
    padding: 2rem 0 2.25rem;
    border-top: 1px solid var(--color-garis);
    background-color: #fff;
}

/*
 * Tepi kiri dan kanan dibuat memudar.
 *
 * Tanpa itu, kartu yang keluar layar terpotong lurus di tepi jendela dan
 * terlihat seperti tata letak yang rusak, bukan seperti pita yang berjalan
 * terus melewati bingkainya.
 */
.jendela {
    overflow: hidden;
    mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}

.rel {
    display: flex;
    gap: 1rem;
    width: max-content;
    animation-name: berjalan;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}

/* Berhenti saat disentuh kursor atau saat salah satu kartunya mendapat fokus
   papan tik — teks yang bergerak tidak mungkin dibaca sampai habis. */
.jendela:hover .rel,
.jendela:focus-within .rel {
    animation-play-state: paused;
}

@keyframes berjalan {
    to { transform: translateX(-50%); }
}

.kartu-ulasan {
    display: block;
    flex: none;
    width: 17rem;
    padding: 0.9rem 1rem 1rem;
    border: 1px solid var(--color-garis);
    border-radius: 0.75rem;
    background-color: #fff;
    transition: border-color 160ms ease, transform 160ms ease;
}

.kartu-ulasan:hover {
    border-color: var(--color-indigo);
    transform: translateY(-2px);
}

.bintang {
    display: inline-flex;
    gap: 0.1rem;
    color: var(--color-emas);
    font-size: 0.7rem;
}

.komentar {
    margin: 0.45rem 0 0.6rem;
    font-size: 0.875rem;
    line-height: 1.45;
    color: var(--color-tinta);

    /* Dipangkas tiga baris. Ulasan panjang akan membuat kartunya meninggi
       sendiri dan merusak garis bawah pita. */
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.penulis {
    display: block;
    font-size: 0.75rem;
    color: color-mix(in srgb, var(--color-tinta) 55%, transparent);
}

/*
 * Pada perangkat yang meminta pengurangan gerak, pitanya berhenti dan berubah
 * menjadi daftar yang dapat digeser sendiri. Isinya tetap terbaca seluruhnya —
 * yang hilang hanya geraknya.
 */
@media (prefers-reduced-motion: reduce) {
    .jendela {
        overflow-x: auto;
        mask-image: none;
        -webkit-mask-image: none;
    }

    .rel {
        animation: none;
    }

    .kartu-ulasan:hover {
        transform: none;
    }
}
</style>
