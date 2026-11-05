<script setup>
/**
 * Bingkai dengan seberkas cahaya yang berputar mengelilingi tepinya.
 *
 * CARA KERJANYA
 *
 * Satu lapisan conic-gradient yang jauh lebih besar daripada bingkainya
 * diputar terus-menerus di belakang isi. Isinya kemudian ditumpuk di atasnya
 * dengan latar sendiri, menyisakan gradien itu terlihat hanya selebar padding
 * bingkai — dua piksel. Yang tampak akhirnya cuma garis tepi yang bercahaya.
 *
 * Tidak ada JavaScript sama sekali, dan tidak ada SVG. Seluruhnya satu
 * animasi CSS yang dijalankan compositor, sehingga tidak membebani thread
 * utama meski berputar tanpa henti.
 *
 * KAPAN DIPAKAI
 *
 * Satu per halaman. Cahaya ini bekerja dengan menarik mata ke satu benda;
 * begitu dua benda sama-sama bercahaya, keduanya berhenti berarti apa-apa.
 */
defineProps({
    /**
     * Mematikan cahaya tanpa melepas bingkainya, sehingga ukuran dan posisi
     * isinya tidak berubah saat sorotan berpindah ke kartu lain.
     */
    aktif: { type: Boolean, default: true },

    /** Jari-jari sudut, menyesuaikan bentuk isi yang dibungkus. */
    jari: { type: String, default: '0px' },
});
</script>

<template>
    <div class="tepi-cahaya" :class="{ padam: !aktif }" :style="{ '--jari': jari }">
        <!--
            Lapisan cahaya dipisahkan dari bingkainya sendiri.

            Pemotongan dilakukan di lapisan ini, bukan di bingkai luar: kalau
            bingkainya yang memotong, bayangan dan gerak naik milik isi di
            dalamnya ikut terpotong.
        -->
        <div class="lapis" aria-hidden="true" />

        <div class="isi">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.tepi-cahaya {
    position: relative;
    padding: 2px;
    border-radius: var(--jari);
    background-color: var(--color-garis);
}

.lapis {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    overflow: hidden;
    pointer-events: none;
}

.lapis::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    width: 180%;
    aspect-ratio: 1;
    translate: -50% -50%;

    /* Dua jeda bening di antara kedua warna. Tanpa itu, yang berputar adalah
       cincin warna penuh — bukan seberkas cahaya yang mengelilingi tepi. */
    background: conic-gradient(
        var(--color-emas),
        transparent 26%,
        var(--color-indigo) 50%,
        transparent 76%,
        var(--color-emas)
    );
    animation: tepi-berputar 5s linear infinite;
}

/* Bingkai yang padam tidak boleh meninggalkan garis abu-abu di sekeliling
   isinya. Yang tersisa hanya dua piksel bening — cukup untuk menjaga ukuran
   dan posisi isi tetap sama dengan kartu yang menyala. */
.padam {
    background-color: transparent;
}

.padam .lapis {
    display: none;
}

/*
 * Sengaja TIDAK memakai overflow: hidden.
 *
 * Isi yang dibungkus komponen ini sudah memotong dirinya sendiri — kartu kos
 * maupun kartu autentikasi keduanya punya overflow: hidden dan sudutnya
 * sendiri. Memotong sekali lagi di sini justru membuang bayangan mereka, dan
 * pada kartu kos bayangan itulah satu-satunya yang memisahkannya dari latar.
 *
 * Gradien yang berputar tetap tertutup karena isinya punya latar sendiri,
 * bukan karena dipotong.
 */
.isi {
    position: relative;
    height: 100%;
    border-radius: calc(var(--jari) - 2px);
}

@keyframes tepi-berputar {
    to { rotate: 360deg; }
}

/* Cahaya yang berputar tanpa henti termasuk gerak yang tidak dipicu
   pengguna. Pada perangkat yang meminta pengurangan gerak, putarannya
   dihentikan dan yang tersisa adalah garis tepi diam berwarna emas. */
@media (prefers-reduced-motion: reduce) {
    .lapis::before {
        animation: none;
        background: var(--color-emas);
    }
}
</style>
