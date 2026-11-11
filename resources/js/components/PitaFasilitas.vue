<!--
    Diadaptasi dari komponen LogoLoop milik Vue Bits:
    https://github.com/DavidHDev/vue-bits
    src/content/Animations/LogoLoop/LogoLoop.vue

    Diubah dari TypeScript ke JavaScript, nama-namanya diterjemahkan, dan
    bagian yang tidak dipakai di sini dibuang: arah tegak, logo gambar,
    tautan, slot, skala saat disentuh, dan pudar tepi berwarna.

    MIT + Commons Clause License Condition v1.0

    Copyright (c) 2025 David Haz

    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, and distribute the Software **as part of an application, website, or product**, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.

    Commons Clause Restriction

    You may use this Software, including for any commercial purpose, **so long as you do not sell, sublicense, or redistribute the components themselves-whether alone, in a bundle, template, or as a ported version.**

    No Warranty

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
    SOFTWARE.
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ikonFasilitas } from '../ikonFasilitas';

/**
 * Pita fasilitas yang berjalan mendatar di bawah kotak pencarian.
 *
 * Memberi tahu sekilas apa saja yang bisa dicari tanpa membuka penyaring.
 * Isinya nama fasilitas dari config/ekost.php, diteruskan halaman lewat prop —
 * komponen ini tidak pernah menulis daftarnya sendiri.
 *
 * MENGAPA JAVASCRIPT, BUKAN ANIMASI CSS SEPERTI PITA ULASAN
 *
 * Pita ulasan menggeser rel sejauh setengah lebarnya, yang hanya mulus bila
 * dua salinan isinya sudah lebih lebar daripada layar. Ulasan cukup panjang
 * untuk itu; sepuluh nama fasilitas tidak — di layar lebar keduanya habis
 * sebelum mencapai tepi kanan dan menyisakan celah kosong. Di sini jumlah
 * salinan dihitung dari lebar jendelanya, dan rel digeser tiap frame.
 *
 * Bonusnya, pita tidak berhenti mendadak saat disentuh kursor. Lajunya
 * melandai ke nol dan melandai lagi saat kursor pergi.
 */
const props = defineProps({
    fasilitas: { type: Array, default: () => [] },
});

/**
 * Piksel per detik. Jauh lebih pelan daripada bawaan LogoLoop (120): ini
 * label yang perlu dibaca, bukan deretan logo yang cukup dikenali bentuknya.
 */
const KECEPATAN = 40;

/** Seberapa lekas laju mengejar tujuannya saat mulai dan saat berhenti. */
const TAU_LANDAI_DETIK = 0.25;

const SALINAN_MINIMUM = 2;

/*
 * Salinan cadangan di luar yang dibutuhkan untuk menutup lebar jendela.
 * Saat urutan pertama sedang keluar di kiri, harus sudah ada urutan utuh
 * yang siap masuk dari kanan.
 */
const SALINAN_CADANGAN = 2;

const jendela = ref(null);
const rel = ref(null);
const urutanPertama = ref(null);

const lebarUrutan = ref(0);
const jumlahSalinan = ref(SALINAN_MINIMUM);
const disentuh = ref(false);
const gerakDikurangi = ref(false);

const butir = computed(() => props.fasilitas.map((nama) => ({ nama, ikon: ikonFasilitas(nama) })));

let bingkai = null;
let waktuTerakhir = null;
let geser = 0;
let laju = 0;
let pengamat = null;
let kueriGerak = null;

async function ukurUlang() {
    await nextTick();

    const lebarJendela = jendela.value?.clientWidth ?? 0;
    const lebar = urutanPertama.value?.getBoundingClientRect().width ?? 0;

    if (lebar > 0) {
        lebarUrutan.value = Math.ceil(lebar);
        jumlahSalinan.value = Math.max(SALINAN_MINIMUM, Math.ceil(lebarJendela / lebar) + SALINAN_CADANGAN);
    }
}

function langkah(waktu) {
    if (waktuTerakhir === null) {
        waktuTerakhir = waktu;
    }

    const selangDetik = Math.max(0, waktu - waktuTerakhir) / 1000;
    waktuTerakhir = waktu;

    const tujuan = disentuh.value ? 0 : KECEPATAN;
    laju += (tujuan - laju) * (1 - Math.exp(-selangDetik / TAU_LANDAI_DETIK));

    const panjang = lebarUrutan.value;

    if (panjang > 0 && rel.value) {
        // Digulung kembali tiap satu urutan. Urutan berikutnya identik, jadi
        // lompatan kembali ke awal tidak terlihat.
        geser = (((geser + laju * selangDetik) % panjang) + panjang) % panjang;
        rel.value.style.transform = `translate3d(${-geser}px, 0, 0)`;
    }

    bingkai = requestAnimationFrame(langkah);
}

function berhenti() {
    cancelAnimationFrame(bingkai);
    bingkai = null;
    waktuTerakhir = null;
}

function mulai() {
    berhenti();

    if (!gerakDikurangi.value) {
        bingkai = requestAnimationFrame(langkah);
    }
}

function pasangPengamat() {
    pengamat?.disconnect();

    if (!jendela.value) {
        return;
    }

    pengamat.observe(jendela.value);

    if (urutanPertama.value) {
        pengamat.observe(urutanPertama.value);
    }
}

/*
 * Pilihan pengurangan gerak dipantau, bukan dibaca sekali. Pengguna yang
 * menyalakannya saat halaman terbuka langsung mendapat pita yang diam, tanpa
 * perlu memuat ulang.
 */
function ikutiPilihanGerak() {
    gerakDikurangi.value = kueriGerak?.matches ?? false;
}

watch(gerakDikurangi, async () => {
    await nextTick();
    pasangPengamat();
    await ukurUlang();
    mulai();
});

watch(
    () => props.fasilitas,
    async () => {
        await nextTick();
        pasangPengamat();
        await ukurUlang();
    },
    { deep: true },
);

onMounted(async () => {
    kueriGerak = window.matchMedia?.('(prefers-reduced-motion: reduce)') ?? null;
    kueriGerak?.addEventListener('change', ikutiPilihanGerak);
    ikutiPilihanGerak();

    pengamat = new ResizeObserver(() => ukurUlang());

    await nextTick();
    pasangPengamat();
    await ukurUlang();
    mulai();

    // Lebar label berubah beberapa piksel setelah fonta selesai dimuat. Tanpa
    // pengukuran ulang, sambungan antarurutan akan tersendat sekali tiap putaran.
    document.fonts?.ready.then(() => ukurUlang());
});

onBeforeUnmount(() => {
    berhenti();
    pengamat?.disconnect();
    kueriGerak?.removeEventListener('change', ikutiPilihanGerak);
});
</script>

<template>
    <div
        v-if="butir.length"
        ref="jendela"
        class="pita-fasilitas"
        :class="!gerakDikurangi && 'bergerak'"
        role="region"
        aria-label="Fasilitas yang bisa dicari"
    >
        <!--
            Pada perangkat yang meminta pengurangan gerak, pitanya diganti
            deretan diam yang dibungkus ke baris berikutnya. Pita diam yang
            tetap satu baris akan memotong butir di tepi kanan, dan butir yang
            terpotong itu tidak akan pernah bergeser masuk untuk dibaca.
        -->
        <ul v-if="gerakDikurangi" class="urutan-diam">
            <li v-for="item in butir" :key="item.nama" class="butir">
                <i :class="`pi ${item.ikon}`" aria-hidden="true" />
                {{ item.nama }}
            </li>
        </ul>

        <div
            v-else
            ref="rel"
            class="rel"
            @mouseenter="disentuh = true"
            @mouseleave="disentuh = false"
        >
            <!--
                Salinan selain yang pertama disembunyikan dari pembaca layar.
                Isinya sama persis, dan tanpa ini setiap fasilitas akan
                dibacakan berkali-kali.
            -->
            <ul
                v-for="salinan in jumlahSalinan"
                :key="salinan"
                :ref="(el) => { if (salinan === 1) urutanPertama = el; }"
                class="urutan"
                :aria-hidden="salinan > 1 ? 'true' : undefined"
            >
                <li v-for="item in butir" :key="item.nama" class="butir">
                    <i :class="`pi ${item.ikon}`" aria-hidden="true" />
                    {{ item.nama }}
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.pita-fasilitas {
    overflow: hidden;
}

/*
 * Tepi kiri dan kanan memudar, sama seperti pita ulasan.
 *
 * Masker dipakai, bukan gradien berwarna seperti di LogoLoop aslinya: masker
 * tidak perlu tahu warna latar di belakangnya, sehingga pita ini tetap benar
 * di atas permukaan apa pun.
 */
.bergerak {
    mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 6%, #000 94%, transparent);
}

.rel {
    display: flex;
    width: max-content;
    will-change: transform;
    user-select: none;
}

.urutan {
    display: flex;
    align-items: center;
}

/* Jaraknya ditaruh sebagai margin kanan tiap butir, bukan gap pada urutan.
   Dengan begitu urutan ikut membawa jarak sesudah butir terakhirnya, dan
   sambungan ke urutan berikutnya berjarak sama dengan butir lainnya. */
.urutan > .butir {
    margin-right: 0.625rem;
}

.urutan-diam {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 0.625rem;
}

.butir {
    display: inline-flex;
    flex: none;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.85rem;
    border: 1px solid var(--color-garis);
    border-radius: 9999px;
    background-color: #fff;
    font-size: 0.875rem;
    white-space: nowrap;
    color: color-mix(in oklab, var(--color-tinta) 80%, transparent);
}

.butir i {
    font-size: 0.8rem;
    color: var(--color-indigo);
}
</style>
