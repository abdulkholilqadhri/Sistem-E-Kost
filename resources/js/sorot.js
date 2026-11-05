/**
 * Direktif v-sorot — cahaya lembut yang mengikuti kursor di atas elemen.
 *
 * Pemakaian:
 *   <article v-sorot>              warna bawaan: emas
 *   <article v-sorot:indigo>       warna lain
 *
 * Gerak geraknya diatur resources/css/app.css lewat atribut data-sorot.
 *
 * MENGAPA BUKAN HOVER BIASA
 *
 * Perubahan warna saat hover menandai bahwa kartunya tersentuh, tetapi tidak
 * menandai di mana. Sorot yang mengikuti kursor membuat kartunya terasa
 * merespons orangnya, bukan sekadar berganti keadaan — dan pada daftar dua
 * puluh empat kartu, itu yang membedakan kartu yang sedang dibaca dari dua
 * puluh tiga lainnya.
 *
 * TIDAK DIPASANG PADA PERANGKAT SENTUH
 *
 * Tanpa kursor, sorot ini tidak akan pernah terlihat, sementara pendengar
 * peristiwanya tetap terpasang pada setiap kartu. Pemeriksaan (hover: hover)
 * membuatnya tidak dipasang sama sekali di ponsel.
 */
const pembersihPerElemen = new WeakMap();

function tidakPerluSorot() {
    if (! window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || ! window.matchMedia('(hover: hover)').matches;
}

export const sorot = {
    mounted(el, binding) {
        if (tidakPerluSorot()) {
            return;
        }

        el.dataset.sorot = binding.arg ?? 'emas';

        /*
         * Posisi disalin ke CSS sekali per bingkai, bukan setiap kali
         * mousemove menyala.
         *
         * Satu gerakan kursor dapat memicu puluhan peristiwa dalam satu
         * bingkai layar, dan hanya yang terakhir yang akan terlihat. Menulis
         * variabel CSS pada tiap peristiwa berarti memaksa peramban menghitung
         * ulang gaya berkali-kali untuk satu gambar yang sama.
         */
        let bingkai = 0;
        let x = 0;
        let y = 0;

        function tulis() {
            bingkai = 0;
            el.style.setProperty('--sorot-x', `${x}px`);
            el.style.setProperty('--sorot-y', `${y}px`);
        }

        function bergerak(peristiwa) {
            const kotak = el.getBoundingClientRect();
            x = peristiwa.clientX - kotak.left;
            y = peristiwa.clientY - kotak.top;

            if (! bingkai) {
                bingkai = requestAnimationFrame(tulis);
            }
        }

        el.addEventListener('mousemove', bergerak);

        pembersihPerElemen.set(el, () => {
            el.removeEventListener('mousemove', bergerak);
            cancelAnimationFrame(bingkai);
        });
    },

    unmounted(el) {
        pembersihPerElemen.get(el)?.();
        pembersihPerElemen.delete(el);
    },
};
