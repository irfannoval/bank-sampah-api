<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import { api } from '../../lib/api';
import { logout } from '../../lib/auth';
import { rp } from '../../lib/format';

const router = useRouter();
const data = ref(null);
const galat = ref('');

onMounted(async () => {
    try {
        data.value = await api.get('/nasabah/beranda');
    } catch (e) {
        galat.value = e.message;
    }
});

async function keluar() {
    await logout();
    router.replace('/login');
}
</script>

<template>
    <Halaman :judul="data ? `Halo, ${data.nama}` : 'Bank Sampah'">
        <template #kanan><button class="min-h-11 px-3 text-sm text-muted" @click="keluar">Keluar</button></template>
        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>

        <div v-if="data" class="card">
            <span class="text-sm text-muted">Saldo kamu</span>
            <strong class="text-4xl" data-testid="saldo">{{ rp(data.saldo) }}</strong>
            <span class="text-sm text-muted">Setoran bulan ini: {{ Number(data.setoran_bulan_ini.total_berat_kg).toLocaleString('id-ID') }} kg ({{ data.setoran_bulan_ini.jumlah }} kali)</span>
            <span class="text-xs text-muted">{{ data.kode_nasabah }}</span>
        </div>

        <div class="flex flex-col gap-3">
            <router-link to="/nasabah/tarik" class="btn btn-primary min-h-16 text-lg">Tarik Saldo</router-link>
            <router-link to="/nasabah/mutasi" class="btn btn-ghost min-h-16 text-lg">Riwayat Saldo</router-link>
            <router-link to="/nasabah/setoran" class="btn btn-ghost min-h-16 text-lg">Setoran Saya</router-link>
            <router-link to="/nasabah/toko-mitra" class="btn btn-ghost min-h-16 text-lg">Toko Mitra</router-link>
        </div>
        <div class="flex justify-center gap-6 text-sm">
            <router-link to="/nasabah/penarikan" class="min-h-11 py-3 text-brand-700 underline">Riwayat penarikan</router-link>
            <router-link to="/ubah-pin" class="min-h-11 py-3 text-brand-700 underline">Ubah PIN</router-link>
        </div>
    </Halaman>
</template>
