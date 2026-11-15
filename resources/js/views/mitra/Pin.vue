<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import Numpad from '../../components/Numpad.vue';
import { api } from '../../lib/api';
import { resetTrx, trx } from '../../lib/transaksi';
import { rp } from '../../lib/format';

const router = useRouter();
const pin = ref('');
const galat = ref('');
const proses = ref(false);

onMounted(() => {
    if (!trx.sesi || !trx.nominal) router.replace('/mitra/scan');
});

function tekan(k) {
    if (proses.value || pin.value.length >= 6) return;
    pin.value += k;
    if (pin.value.length === 6) kirim();
}
function hapus() {
    pin.value = pin.value.slice(0, -1);
}

async function kirim() {
    proses.value = true;
    galat.value = '';
    try {
        trx.hasil = await api.post(
            `/mitra/transaksi/${trx.sesi.sesi_id}/konfirmasi`,
            { nominal: trx.nominal, pin: pin.value },
            { idem: trx.kunci }
        );
        router.replace('/mitra/hasil');
    } catch (e) {
        pin.value = '';
        proses.value = false;
        galat.value = e.message;
        const sisa = e.data && e.data.error && e.data.error.sisa_percobaan;
        if (e.kode === 'PIN_SALAH' && sisa !== undefined) galat.value += ` Sisa percobaan: ${sisa}.`;
        if (['SESI_KADALUARSA', 'AKUN_TERKUNCI', 'KARTU_TIDAK_AKTIF', 'AKUN_NONAKTIF'].includes(e.kode)) {
            // Transaksi tidak bisa dilanjutkan: mulai dari awal.
            const pesan = e.message;
            resetTrx();
            sessionStorage.setItem('bs_info', pesan);
            router.replace('/mitra/scan');
        } else if (e.kode === 'SALDO_KURANG') {
            router.replace('/mitra/nominal');
        }
    }
}
</script>

<template>
    <Halaman judul="PIN Nasabah" kembali="/mitra/nominal">
        <div class="card text-center">
            <span class="text-sm text-muted">Pembayaran di toko</span>
            <strong class="text-3xl">{{ rp(trx.nominal) }}</strong>
            <span class="text-sm text-muted">Serahkan HP ke nasabah, lalu minta masukkan PIN 6 digit.</span>
        </div>

        <div class="flex justify-center gap-3.5 py-1" aria-label="Isian PIN" data-testid="titik-pin">
            <span v-for="i in 6" :key="i" class="h-4 w-4 rounded-full border-2 border-gray-500" :class="{ 'bg-brand-600 border-brand-600': i <= pin.length }"></span>
        </div>

        <p v-if="galat" class="pesan-error text-center" role="alert">{{ galat }}</p>
        <p v-if="proses" class="text-center text-muted">Memproses...</p>

        <Numpad @tekan="tekan" @hapus="hapus" />
    </Halaman>
</template>
