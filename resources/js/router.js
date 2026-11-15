import { createRouter, createWebHistory } from 'vue-router';
import { auth, rumah } from './lib/auth';
import Login from './views/Login.vue';
import Segera from './views/Segera.vue';
import MitraBeranda from './views/mitra/Beranda.vue';
import MitraScan from './views/mitra/Scan.vue';
import MitraProfil from './views/mitra/Profil.vue';
import MitraNominal from './views/mitra/Nominal.vue';
import MitraPin from './views/mitra/Pin.vue';
import MitraHasil from './views/mitra/Hasil.vue';
import MitraRiwayat from './views/mitra/Riwayat.vue';
import MitraPencairan from './views/mitra/Pencairan.vue';

const mitra = { role: 'mitra' };

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', redirect: () => (auth.user ? rumah(auth.user.role) : '/login') },
        { path: '/login', component: Login, meta: { publik: true } },
        { path: '/mitra', component: MitraBeranda, meta: mitra },
        { path: '/mitra/scan', component: MitraScan, meta: mitra },
        { path: '/mitra/profil', component: MitraProfil, meta: mitra },
        { path: '/mitra/nominal', component: MitraNominal, meta: mitra },
        { path: '/mitra/pin', component: MitraPin, meta: mitra },
        { path: '/mitra/hasil', component: MitraHasil, meta: mitra },
        { path: '/mitra/riwayat', component: MitraRiwayat, meta: mitra },
        { path: '/mitra/pencairan', component: MitraPencairan, meta: mitra },
        { path: '/nasabah', component: Segera, meta: { role: 'nasabah' } },
        { path: '/admin', component: Segera, meta: { role: 'admin' } },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});

router.beforeEach((to) => {
    if (to.meta.publik) {
        return auth.token && auth.user ? rumah(auth.user.role) : true;
    }
    if (!auth.token || !auth.user) return '/login';

    const role = auth.user.role === 'admin_utama' ? 'admin' : auth.user.role;
    if (to.meta.role && to.meta.role !== role) return rumah(auth.user.role);
    return true;
});

export default router;
