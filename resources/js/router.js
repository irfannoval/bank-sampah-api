import { createRouter, createWebHistory } from 'vue-router';
import { auth, rumah } from './lib/auth';
import Login from './views/Login.vue';
const UbahPin = () => import('./views/UbahPin.vue');
const MitraBeranda = () => import('./views/mitra/Beranda.vue');
const MitraScan = () => import('./views/mitra/Scan.vue');
const MitraProfil = () => import('./views/mitra/Profil.vue');
const MitraNominal = () => import('./views/mitra/Nominal.vue');
const MitraPin = () => import('./views/mitra/Pin.vue');
const MitraHasil = () => import('./views/mitra/Hasil.vue');
const MitraRiwayat = () => import('./views/mitra/Riwayat.vue');
const MitraPencairan = () => import('./views/mitra/Pencairan.vue');
const AdminSetoran = () => import('./views/admin/Setoran.vue');
const AdminRiwayatSetoran = () => import('./views/admin/RiwayatSetoran.vue');
const AdminNasabah = () => import('./views/admin/Nasabah.vue');
const AdminNasabahDetail = () => import('./views/admin/NasabahDetail.vue');
const AdminJenisSampah = () => import('./views/admin/JenisSampah.vue');
const AdminMitra = () => import('./views/admin/Mitra.vue');
const AdminTransaksi = () => import('./views/admin/TransaksiMitra.vue');
const AdminPencairan = () => import('./views/admin/Pencairan.vue');
const AdminPenarikan = () => import('./views/admin/Penarikan.vue');
const AdminLaporan = () => import('./views/admin/Laporan.vue');
const AdminPengaturan = () => import('./views/admin/Pengaturan.vue');
const AdminSistem = () => import('./views/admin/Sistem.vue');
const NasabahBeranda = () => import('./views/nasabah/Beranda.vue');
const NasabahMutasi = () => import('./views/nasabah/Mutasi.vue');
const NasabahSetoran = () => import('./views/nasabah/Setoran.vue');
const NasabahTokoMitra = () => import('./views/nasabah/TokoMitra.vue');
const NasabahTarik = () => import('./views/nasabah/Tarik.vue');
const NasabahPenarikan = () => import('./views/nasabah/Penarikan.vue');

const mitra = { role: 'mitra' };
const admin = { role: 'admin' };
const utama = { role: 'admin', utama: true };
const nasabah = { role: 'nasabah' };

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
        { path: '/nasabah', component: NasabahBeranda, meta: nasabah },
        { path: '/nasabah/mutasi', component: NasabahMutasi, meta: nasabah },
        { path: '/nasabah/setoran', component: NasabahSetoran, meta: nasabah },
        { path: '/nasabah/toko-mitra', component: NasabahTokoMitra, meta: nasabah },
        { path: '/nasabah/tarik', component: NasabahTarik, meta: nasabah },
        { path: '/nasabah/penarikan', component: NasabahPenarikan, meta: nasabah },
        { path: '/ubah-pin', component: UbahPin },
        { path: '/admin', redirect: '/admin/setoran' },
        { path: '/admin/setoran', component: AdminSetoran, meta: admin },
        { path: '/admin/riwayat-setoran', component: AdminRiwayatSetoran, meta: admin },
        { path: '/admin/nasabah', component: AdminNasabah, meta: admin },
        { path: '/admin/nasabah/:id(\\d+)', component: AdminNasabahDetail, meta: admin },
        { path: '/admin/jenis-sampah', component: AdminJenisSampah, meta: admin },
        { path: '/admin/mitra', component: AdminMitra, meta: admin },
        { path: '/admin/transaksi', component: AdminTransaksi, meta: admin },
        { path: '/admin/pencairan', component: AdminPencairan, meta: admin },
        { path: '/admin/penarikan', component: AdminPenarikan, meta: admin },
        { path: '/admin/laporan', component: AdminLaporan, meta: admin },
        { path: '/admin/pengaturan', component: AdminPengaturan, meta: utama },
        { path: '/admin/sistem', component: AdminSistem, meta: utama },
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
    if (to.meta.utama && auth.user.role !== 'admin_utama') return rumah(auth.user.role);
    return true;
});

export default router;
