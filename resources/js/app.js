import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import router from './router';
import tema from './theme';
import App from './App.vue';

createApp(App)
    .use(createPinia())
    .use(router)
    .use(PrimeVue, { theme: tema, ripple: true })
    .use(ToastService)
    .use(ConfirmationService)
    .directive('tooltip', Tooltip)
    .mount('#app');
