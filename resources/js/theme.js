import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

/**
 * Tema E-Kost.
 *
 * Paletnya diambil dari sarung sutera Mandar — kain tenun khas Sulawesi Barat
 * dengan kotak-kotak indigo tua, maroon, dan benang emas. Dipilih supaya
 * tampilan aplikasi ini terasa milik Majene, bukan tema bawaan yang bisa
 * dipakai aplikasi kos mana pun.
 *
 * Indigo menjadi warna utama (tombol, tautan, keadaan aktif).
 * Maroon disimpan untuk peringatan dan tindakan merusak.
 * Emas dipakai sangat hemat: hanya untuk angka jarak, satu-satunya informasi
 * yang paling menentukan keputusan pengguna. Kalau emas muncul di banyak
 * tempat, ia berhenti menandakan apa pun.
 */
const presetEkost = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#eef2f8',
            100: '#d6dfee',
            200: '#afc0dc',
            300: '#849ec7',
            400: '#5a7baf',
            500: '#3b5c94',
            600: '#28467f',
            700: '#1f3766',
            800: '#192c50',
            900: '#16233f',
            950: '#0e1728',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.700}',
                    contrastColor: '#ffffff',
                    hoverColor: '{primary.600}',
                    activeColor: '{primary.800}',
                },
                surface: {
                    0: '#ffffff',
                    50: '#f6f8fa',
                    100: '#e9edf2',
                    200: '#d7dee7',
                    300: '#b9c4d2',
                    400: '#8e9cae',
                    500: '#6b7a8c',
                    600: '#51606f',
                    700: '#3d4a57',
                    800: '#2b3540',
                    900: '#1c242c',
                    950: '#11171d',
                },
                formField: {
                    background: '#ffffff',
                    borderColor: '{surface.300}',
                    hoverBorderColor: '{surface.400}',
                    focusBorderColor: '{primary.600}',
                },
            },
        },
    },
    components: {
        button: {
            // Tombol berbentuk pil, mengikuti referensi desain yang diberikan.
            // Hierarki antartombol dijaga lewat isi dan warna — tombol utama
            // berisi penuh, tombol pendamping bergaris saja — bukan lewat
            // perbedaan sudut.
            root: {
                borderRadius: '999px',
                paddingX: '1.25rem',
                gap: '0.5rem',
            },
        },
        card: {
            // Sudut siku, mengikuti referensi desain. Yang memisahkan kartu
            // dari latar adalah bayangan, bukan garis tepi maupun lengkungan.
            root: {
                borderRadius: '0',
                shadow: '0 12px 28px -16px rgba(22, 35, 63, 0.38)',
            },
            body: {
                padding: '1.25rem',
            },
        },
        inputtext: {
            root: {
                borderRadius: '8px',
            },
        },
    },
});

export default {
    preset: presetEkost,
    options: {
        darkModeSelector: '.gelap',

        /*
         * Urutan lapisan CSS menentukan siapa yang menang saat Tailwind dan
         * PrimeVue mengatur properti yang sama.
         *
         * 'utilities' diletakkan paling akhir supaya kelas Tailwind seperti
         * w-full atau mt-4 dapat menimpa gaya bawaan komponen PrimeVue.
         * Tanpa urutan ini, penyesuaian tata letak pada komponen PrimeVue
         * akan diabaikan tanpa pesan apa pun — dan dugaan pertama orang
         * biasanya "kelasnya salah ketik", bukan "urutan lapisannya terbalik".
         *
         * Urutan yang sama harus dideklarasikan di resources/css/app.css.
         */
        cssLayer: {
            name: 'primevue',
            order: 'theme, base, primevue, components, utilities',
        },
    },
};
