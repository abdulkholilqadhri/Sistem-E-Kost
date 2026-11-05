/**
 * Direktif v-magnet — tombol yang bergeser sedikit menyambut kursor.
 *
 * Pemakaian:
 *   <Button v-magnet label="Ajukan pesanan" />
 *
 * SATU TOMBOL SAJA PER HALAMAN
 *
 * Gerakan ini bekerja dengan menarik perhatian ke satu tindakan. Kalau semua
 * tombol bermagnet, tidak ada lagi yang menonjol — dan halaman penuh tombol
 * yang bergerak-gerak terasa gelisah, bukan hidup.
 *
 * GESERANNYA DIBATASI ENAM PIKSEL
 *
 * Cukup untuk terasa, tidak cukup untuk membuat tombolnya lari dari orang
 * yang hendak menekannya. Tombol yang menghindar adalah lelucon yang hanya
 * lucu sekali.
 */
const pembersihPerElemen = new WeakMap();

const TARIK_PIKSEL = 6;
const JANGKAUAN_PIKSEL = 120;

function tidakPerluMagnet() {
    if (! window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || ! window.matchMedia('(hover: hover)').matches;
}

export const magnet = {
    mounted(el) {
        if (tidakPerluMagnet()) {
            return;
        }

        /*
         * Pendengarnya dipasang pada induk, bukan pada tombolnya sendiri.
         *
         * Kalau dipasang pada tombol, geserannya baru dimulai setelah kursor
         * sudah berada di atasnya — dan pada saat itu tidak ada lagi yang
         * perlu disambut. Yang dicari justru gerakan sebelum kursor sampai.
         */
        const wilayah = el.parentElement;

        if (! wilayah) {
            return;
        }

        el.style.transition = 'transform 180ms cubic-bezier(0.22, 1, 0.36, 1)';

        let bingkai = 0;
        let x = 0;
        let y = 0;

        function tulis() {
            bingkai = 0;
            el.style.transform = `translate(${x}px, ${y}px)`;
        }

        function bergerak(peristiwa) {
            const kotak = el.getBoundingClientRect();
            const dx = peristiwa.clientX - (kotak.left + kotak.width / 2);
            const dy = peristiwa.clientY - (kotak.top + kotak.height / 2);
            const jarak = Math.hypot(dx, dy) || 1;

            // Makin jauh kursornya, makin lemah tarikannya — di luar
            // jangkauan, tombolnya diam sama sekali.
            const kuat = Math.min(1, JANGKAUAN_PIKSEL / jarak);

            x = (dx / jarak) * TARIK_PIKSEL * kuat;
            y = (dy / jarak) * TARIK_PIKSEL * kuat;

            if (! bingkai) {
                bingkai = requestAnimationFrame(tulis);
            }
        }

        function pulang() {
            cancelAnimationFrame(bingkai);
            bingkai = 0;
            el.style.transform = '';
        }

        wilayah.addEventListener('mousemove', bergerak);
        wilayah.addEventListener('mouseleave', pulang);

        pembersihPerElemen.set(el, () => {
            wilayah.removeEventListener('mousemove', bergerak);
            wilayah.removeEventListener('mouseleave', pulang);
            cancelAnimationFrame(bingkai);
        });
    },

    unmounted(el) {
        pembersihPerElemen.get(el)?.();
        pembersihPerElemen.delete(el);
    },
};
