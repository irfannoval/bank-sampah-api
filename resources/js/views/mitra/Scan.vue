<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import QrScanner from '../../components/QrScanner.vue';
import { api } from '../../lib/api';
import { resetTrx, trx } from '../../lib/transaksi';

const router = useRouter();
const kode = ref('');
const galat = ref(sessionStorage.getItem('bs_info') || '');
sessionStorage.removeItem('bs_info');
const proses = ref(false);

resetTrx();

async function cari(qrToken) {
    if (proses.value || !qrToken) return;
    proses.value = true;
    galat.value = '';
    try {
        const r = await api.post('/mitra/transaksi/mulai', { qr_token: qrToken });
        trx.sesi = { sesi_id: r.sesi_id, berlaku_sampai: r.berlaku_sampai };
        trx.nasabah = r.nasabah;
        router.push('/mitra/profil');
    } catch (e) {
        galat.value = e.message;
        proses.value = false;
    }
}
</script>

<template>
    <Halaman judul="Scan Kartu Nasabah" kembali="/mitra">
        <QrScanner :aktif="!proses" @terbaca="cari" />

        <p v-if="proses" class="text-center text-muted">Memeriksa kartu...</p>
        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>

        <div class="text-center text-sm text-muted">atau</div>
        <form class="flex flex-col gap-3" @submit.prevent="cari(kode.trim())">
            <div>
                <label class="label" for="kode">Ketik atau tempel kode kartu</label>
                <input id="kode" v-model="kode" class="input" autocomplete="off" autocapitalize="off" placeholder="Kode di kartu">
            </div>
            <button class="btn btn-ghost" :disabled="proses || !kode.trim()">Cari nasabah</button>
        </form>
    </Halaman>
</template>
