/**
 * Konfeti sekali jalan, untuk satu momen yang memang berarti.
 *
 * Dipakai hanya saat penyewa berhasil mengajukan pemesanan. Itu satu-satunya
 * tindakan di E-Kost yang betul-betul berarti bagi penyewa — selebihnya hanya
 * menelusuri dan membandingkan. Kalau cuma satu tempat yang boleh dirayakan,
 * tempatnya di sana.
 *
 * DITULIS SENDIRI, BUKAN MEMUAT PUSTAKA
 *
 * Seluruhnya di bawah seratus baris. Pustaka konfeti mana pun berukuran lebih
 * besar daripada ini, dan menambah satu dependensi demi dua setengah detik
 * animasi bukan pertukaran yang masuk akal.
 *
 * KANVASNYA DIBUANG SETELAH SELESAI
 *
 * Kanvas melayang setinggi layar yang dibiarkan tertinggal akan menutupi
 * seluruh halaman. pointer-events: none mencegahnya menelan klik selama
 * berjalan, dan remove() memastikan ia tidak tertinggal sesudahnya.
 */
const WARNA = ['#c8922a', '#1f3766', '#9e2b2b', '#1f5f4a', '#28467f'];

const JUMLAH = 90;
const UMUR_MAKSIMUM = 150;

export function taburKonfeti() {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const kanvas = document.createElement('canvas');

    kanvas.style.cssText = [
        'position:fixed',
        'inset:0',
        'width:100%',
        'height:100%',
        'pointer-events:none',
        'z-index:9999',
    ].join(';');

    document.body.appendChild(kanvas);

    const ktx = kanvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const lebar = window.innerWidth;
    const tinggi = window.innerHeight;

    kanvas.width = lebar * dpr;
    kanvas.height = tinggi * dpr;
    ktx.setTransform(dpr, 0, 0, dpr, 0, 0);

    // Disebar dari sepertiga atas layar, bukan dari tengah: di sanalah pesan
    // keberhasilan muncul, dan konfetinya harus terlihat berasal dari situ.
    const asalY = tinggi * 0.32;

    const butir = Array.from({ length: JUMLAH }, (_, i) => ({
        x: lebar / 2 + (Math.random() - 0.5) * 220,
        y: asalY + (Math.random() - 0.5) * 40,
        vx: (Math.random() - 0.5) * 9,
        vy: -Math.random() * 11 - 4,
        panjang: 6 + Math.random() * 7,
        sudut: Math.random() * Math.PI,
        putar: (Math.random() - 0.5) * 0.32,
        warna: WARNA[i % WARNA.length],
        umur: 0,
    }));

    function bingkai() {
        ktx.clearRect(0, 0, lebar, tinggi);

        let hidup = false;

        butir.forEach((b) => {
            b.umur += 1;
            b.vy += 0.3;
            b.vx *= 0.995;
            b.x += b.vx;
            b.y += b.vy;
            b.sudut += b.putar;

            const pudar = Math.max(0, 1 - b.umur / UMUR_MAKSIMUM);

            if (pudar <= 0) {
                return;
            }

            hidup = true;

            ktx.save();
            ktx.translate(b.x, b.y);
            ktx.rotate(b.sudut);
            ktx.globalAlpha = pudar;
            ktx.fillStyle = b.warna;
            ktx.fillRect(-b.panjang / 2, -b.panjang / 4, b.panjang, b.panjang / 2);
            ktx.restore();
        });

        if (hidup) {
            requestAnimationFrame(bingkai);
        } else {
            kanvas.remove();
        }
    }

    requestAnimationFrame(bingkai);
}
