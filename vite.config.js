import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Huruf dimuat lewat <link> di resources/views/app.blade.php.
            // Opsi fonts bawaan plugin tidak menghasilkan @font-face pada
            // proyek ini, sehingga halaman jatuh ke huruf bawaan sistem.
        }),
        tailwindcss(),
        vue({
            template: {
                /*
                 * Secara bawaan, plugin Vue mengubah atribut src pada <img>
                 * di dalam template menjadi impor modul, lalu Vite mencoba
                 * menyelesaikannya saat membangun. Berkas di public/ tidak
                 * ikut dibundel — ia dilayani apa adanya oleh server — jadi
                 * penyelesaiannya gagal dengan "Failed to resolve import".
                 *
                 * includeAbsolute: false membuat path yang diawali garis
                 * miring dibiarkan sebagai alamat biasa.
                 *
                 * base: null berarti tidak ada awalan yang ditambahkan pada
                 * path relatif; keduanya adalah konfigurasi yang dianjurkan
                 * Laravel untuk Vue di atas Vite.
                 */
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
