import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import router from './router';
import tema from './theme';
import { muncul } from './animasi';
import App from './App.vue';

const aplikasi = createApp(App)
    .use(createPinia())
    .use(router)
    .use(PrimeVue, { theme: tema, ripple: true })
    .use(ToastService)
    .use(ConfirmationService)
    .directive('tooltip', Tooltip)
    .directive('muncul', muncul);

aplikasi.mount('#app');

/*
 * Menutup pramuat yang dipasang di resources/views/app.blade.php.
 *
 * Ditunggu sampai router menyelesaikan navigasi pertama, bukan langsung
 * setelah mount. Komponen halaman dimuat secara malas, sehingga sesaat
 * setelah mount isinya masih kosong — menutup pramuat di titik itu akan
 * memperlihatkan halaman putih sebelum halamannya sendiri muncul.
 *
 * isReady() juga selesai bila navigasinya gagal, sehingga pramuat tetap
 * tertutup pada halaman yang bermasalah.
 */
router.isReady().finally(() => {
    window.tutupPramuat?.();
});
