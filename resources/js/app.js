import '../css/app.css';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { setUnauthorizedHandler } from './lib/api';
import { bersihkanSesi } from './lib/auth';

// Token kedaluwarsa atau dicabut: kembali ke halaman masuk.
setUnauthorizedHandler(() => {
    bersihkanSesi();
    router.push('/login');
});

createApp(App).use(router).mount('#app');
