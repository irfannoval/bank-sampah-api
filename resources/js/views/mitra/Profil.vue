<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import { trx } from '../../lib/transaksi';
import { jam, rp } from '../../lib/format';

const router = useRouter();
onMounted(() => {
    if (!trx.sesi) router.replace('/mitra/scan');
});
</script>

<template>
    <Halaman judul="Profil Nasabah" kembali="/mitra/scan">
        <template v-if="trx.nasabah">
            <div class="card">
                <span class="text-sm text-muted">Nasabah</span>
                <strong class="text-2xl">{{ trx.nasabah.nama }}</strong>
                <span class="text-muted">{{ trx.nasabah.kode_nasabah }}</span>
            </div>
            <div class="card">
                <span class="text-sm text-muted">Saldo</span>
                <strong class="text-4xl">{{ rp(trx.nasabah.saldo) }}</strong>
                <span class="text-sm text-muted">Sesi berlaku sampai {{ jam(trx.sesi.berlaku_sampai) }}</span>
            </div>
            <div class="flex-1"></div>
            <router-link to="/mitra/nominal" class="btn btn-primary">Lanjut input nominal</router-link>
            <router-link to="/mitra" class="btn btn-ghost">Batal</router-link>
        </template>
    </Halaman>
</template>
