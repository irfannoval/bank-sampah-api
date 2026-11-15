<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import { api } from '../../lib/api';
import { resetTrx, trx } from '../../lib/transaksi';
import { rp } from '../../lib/format';

const router = useRouter();
const batalAktif = ref(false);
const alasan = ref('');
const galat = ref('');
const proses = ref(false);
const dibatalkan = ref(false);

onMounted(() => {
    if (!trx.hasil) router.replace('/mitra');
});

function selesai() {
    resetTrx();
    router.replace('/mitra');
}

async function batalkan() {
    proses.value = true;
    galat.value = '';
    try {
        await api.post(`/mitra/transaksi/${trx.hasil.transaksi_id}/batal`, { alasan: alasan.value.trim() });
        dibatalkan.value = true;
        batalAktif.value = false;
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <Halaman :judul="dibatalkan ? 'Transaksi Dibatalkan' : 'Transaksi Berhasil'">
        <div v-if="trx.hasil" class="card text-center">
            <strong class="text-2xl" :class="dibatalkan ? 'text-red-700' : 'text-brand-600'">{{ dibatalkan ? 'Dibatalkan' : 'Berhasil' }}</strong>
            <strong class="text-4xl">{{ rp(trx.hasil.nominal) }}</strong>
            <span>{{ trx.nasabah?.nama }}</span>
            <span v-if="!dibatalkan" class="text-sm text-muted">Sisa saldo nasabah: {{ rp(trx.hasil.saldo_nasabah_sesudah) }}</span>
            <span v-else class="text-sm text-muted">Saldo nasabah sudah dikembalikan.</span>
        </div>

        <div class="flex-1"></div>

        <form v-if="batalAktif" class="card" @submit.prevent="batalkan">
            <label class="label" for="alasan">Alasan pembatalan</label>
            <input id="alasan" v-model="alasan" class="input" placeholder="Contoh: salah input nominal">
            <p v-if="galat" class="pesan-error mt-3" role="alert">{{ galat }}</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <button type="button" class="btn btn-ghost" @click="batalAktif = false">Kembali</button>
                <button class="btn btn-danger" :disabled="proses || alasan.trim().length < 3">Batalkan</button>
            </div>
        </form>

        <template v-else>
            <button class="btn btn-primary" @click="selesai">Selesai</button>
            <button v-if="!dibatalkan" class="btn btn-ghost" @click="batalAktif = true">Batalkan transaksi</button>
        </template>
    </Halaman>
</template>
