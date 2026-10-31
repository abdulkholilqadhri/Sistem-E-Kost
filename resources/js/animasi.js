/**
 * Direktif v-muncul — animasi masuk untuk sebuah elemen.
 *
 * Pemakaian:
 *   <div v-muncul>                          jenis bawaan: naik
 *   <h1 v-muncul:selubung>                  jenis tertentu
 *   <div v-muncul="{ nama: 'naik', jeda: 120 }">   dengan penundaan
 *
 * Jenis yang tersedia: naik, kiri, kanan, mekar, selubung, kabur.
 * Definisi geraknya ada di resources/css/app.css.
 *
 * MENGAPA MEMAKAI IntersectionObserver
 *
 * Animasi dijalankan saat elemennya benar-benar masuk layar, bukan saat
 * halaman dimuat. Tanpa itu, seluruh animasi di halaman panjang sudah
 * selesai sebelum pengguna sempat menggulir ke sana — yang dilihat hanya
 * elemen diam, dan gerakannya terbuang percuma.
 *
 * MENGHORMATI PERMINTAAN PENGURANGAN GERAK
 *
 * Bila sistem pengguna meminta pengurangan gerak, atribut penanda tidak
 * dipasang sama sekali. Elemennya tampil apa adanya sejak awal — bukan
 * tampil lalu dianimasikan dengan durasi nol, yang masih menyisakan
 * kedipan.
 */
const pengamatPerElemen = new WeakMap();

function inginGerakDikurangi() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * Menunggu pramuat menyingkir sebelum animasi pertama dijalankan.
 *
 * Pramuat menutupi layar selama tiga detik, jauh lebih lama daripada animasi
 * masuk mana pun. Tanpa penundaan ini, seluruh animasi di bagian atas halaman
 * sudah selesai di baliknya, dan yang dilihat pengguna saat pramuat terangkat
 * adalah halaman yang sudah diam.
 *
 * Penandanya dipasang oleh skrip sebaris di resources/views/app.blade.php.
 * Dua jalan dipakai sekaligus — atribut untuk keadaan yang sudah terjadi,
 * peristiwa untuk yang belum — karena modul ini bisa dijalankan sebelum atau
 * sesudah pramuat menutup, bergantung pada kecepatan unduhan bundel.
 *
 * Batas waktunya lebih panjang daripada jaring pengaman pramuat (10 detik),
 * sehingga ia hanya berlaku bila penandanya benar-benar tidak pernah datang.
 */
const layarSiap = new Promise((selesai) => {
    if (document.documentElement.dataset.pramuat === 'selesai') {
        selesai();

        return;
    }

    const batas = setTimeout(selesai, 11000);

    window.addEventListener(
        'pramuat-selesai',
        () => {
            clearTimeout(batas);
            selesai();
        },
        { once: true },
    );
});

/**
 * Menjalankan animasinya, lalu melepas penandanya setelah selesai.
 *
 * Penanda dilepas, bukan dibiarkan, karena ia membawa will-change: transform
 * yang memaksa peramban menyiapkan lapisan komposit tersendiri untuk tiap
 * elemen. Pada halaman dengan dua puluh kartu, membiarkannya berarti dua
 * puluh lapisan yang tidak lagi berguna setelah animasinya habis.
 *
 * Melepasnya aman: animasinya berakhir pada keadaan normal (opacity 1, tanpa
 * transform), dan aturan opacity: 0 ikut terlepas bersama penandanya.
 */
function bangkitkan(el) {
    el.addEventListener(
        'animationend',
        () => {
            delete el.dataset.muncul;
            el.classList.remove('muncul-aktif');
            el.style.removeProperty('--muncul-jeda');
        },
        { once: true },
    );

    el.classList.add('muncul-aktif');
}

export const muncul = {
    mounted(el, binding) {
        if (inginGerakDikurangi()) {
            return;
        }

        const pengaturan = typeof binding.value === 'object' && binding.value !== null
            ? binding.value
            : {};

        const nama = pengaturan.nama ?? binding.arg ?? 'naik';
        const jeda = pengaturan.jeda ?? 0;

        el.dataset.muncul = nama;
        el.style.setProperty('--muncul-jeda', `${jeda}ms`);

        const pengamat = new IntersectionObserver(
            (entri) => {
                entri.forEach((satu) => {
                    if (! satu.isIntersecting) {
                        return;
                    }

                    // Sekali tampil, cukup. Mengamati terus akan membuat
                    // elemennya berkedip setiap kali digulir keluar-masuk.
                    pengamat.unobserve(satu.target);

                    layarSiap.then(() => bangkitkan(satu.target));
                });
            },
            {
                threshold: 0.1,

                // Sedikit masuk ke dalam layar sebelum dipicu, supaya
                // gerakannya tidak terjadi tepat di garis tepi dan terlihat
                // separuh.
                rootMargin: '0px 0px -48px 0px',
            },
        );

        pengamat.observe(el);
        pengamatPerElemen.set(el, pengamat);
    },

    unmounted(el) {
        pengamatPerElemen.get(el)?.disconnect();
        pengamatPerElemen.delete(el);
    },
};
