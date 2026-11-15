<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import Numpad from '../../components/Numpad.vue';
import { buatKunci } from '../../lib/api';
import { trx } from '../../lib/transaksi';
import { rp } from '../../lib/format';

const router = useRouter();
const angka = ref(trx.nominal || '');

onMounted(() => {
    if (!trx.sesi) router.replace('/mitra/scan');
});

const nilai = computed(() => Number(angka.value || 0));
const kurang = computed(() => trx.nasabah && nilai.value > Number(trx.nasabah.saldo));

function tekan(k) {
    const baru = (angka.value + k).replace(/^0+/, '');
    if (baru.length <= 9) angka.value = baru;
}
function hapus() {
    angka.value = angka.value.slice(0, -1);
}
function lanjut() {
    trx.nominal = angka.value;
    trx.kunci = buatKunci(); // satu kunci per transaksi, aman bila permintaan terkirim ulang
    router.push('/mitra/pin');
}
</script>

<template>
    <Halaman judul="Input Nominal" kembali="/mitra/profil">
        <div v-if="trx.nasabah" class="card">
            <span class="text-sm text-muted">Nasabah</span>
            <strong class="text-lg">{{ trx.nasabah.nama }}</strong>
            <span class="text-sm text-muted">Saldo: {{ rp(trx.nasabah.saldo) }}</span>
        </div>

        <div class="py-2 text-center text-4xl font-bold" data-testid="nominal">{{ rp(nilai) }}</div>
        <p v-if="kurang" class="pesan-error text-center" role="alert">Saldo nasabah tidak cukup.</p>

        <Numpad tombol-extra="000" @tekan="tekan" @hapus="hapus" />

        <button class="btn btn-primary" :disabled="nilai <= 0 || kurang" @click="lanjut">Lanjut, minta PIN nasabah</button>
    </Halaman>
</template>
