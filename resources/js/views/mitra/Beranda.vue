<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import { api } from '../../lib/api';
import { logout } from '../../lib/auth';
import { resetTrx } from '../../lib/transaksi';
import { rp } from '../../lib/format';

const router = useRouter();
const data = ref(null);
const galat = ref('');

onMounted(async () => {
    resetTrx();
    try {
        data.value = await api.get('/mitra/beranda');
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
    <Halaman :judul="data?.nama_toko || 'Toko Mitra'">
        <template #kanan>
            <button class="min-h-11 px-3 text-sm text-muted" @click="keluar">Keluar</button>
        </template>

        <p v-if="galat" class="pesan-error">{{ galat }}</p>

        <div v-if="data" class="card">
            <span class="text-sm text-muted">Saldo tagihan</span>
            <strong class="text-4xl">{{ rp(data.saldo_tagihan) }}</strong>
            <span class="text-sm text-muted">Sedang diajukan pencairan: {{ rp(data.sedang_diajukan) }}</span>
            <span class="mt-1 text-sm text-muted">Hari ini: {{ data.hari_ini.jumlah }} transaksi, {{ rp(data.hari_ini.total) }}</span>
        </div>

        <router-link to="/mitra/scan" class="btn btn-primary min-h-20 text-xl">Terima Pembayaran</router-link>

        <div class="grid grid-cols-2 gap-3">
            <router-link to="/mitra/riwayat" class="btn btn-ghost">Riwayat</router-link>
            <router-link to="/mitra/pencairan" class="btn btn-ghost">Pencairan</router-link>
        </div>
        <router-link to="/ubah-pin" class="min-h-11 py-3 text-center text-sm text-brand-700 underline">Ubah PIN</router-link>
    </Halaman>
</template>
