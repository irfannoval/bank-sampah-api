<script setup>
import { onMounted, ref } from 'vue';
import Halaman from '../../components/Halaman.vue';
import { api, buatKunci } from '../../lib/api';
import { rp, tanggal } from '../../lib/format';

const beranda = ref(null);
const daftar = ref([]);
const galat = ref('');
const info = ref('');
const proses = ref(false);

const label = { pending: 'Menunggu', disetujui: 'Disetujui', dibayar: 'Sudah dibayar', ditolak: 'Ditolak' };
const warna = { pending: 'text-amber-700', disetujui: 'text-blue-700', dibayar: 'text-brand-600', ditolak: 'text-red-700' };

async function muat() {
    try {
        beranda.value = await api.get('/mitra/beranda');
        daftar.value = (await api.get('/mitra/pencairan')).data;
    } catch (e) {
        galat.value = e.message;
    }
}
onMounted(muat);

async function ajukan() {
    proses.value = true;
    galat.value = '';
    info.value = '';
    try {
        const r = await api.post('/mitra/pencairan', {}, { idem: buatKunci() });
        info.value = `Pengajuan ${rp(r.nominal)} terkirim. Admin akan memprosesnya.`;
        await muat();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <Halaman judul="Pencairan" kembali="/mitra">
        <div v-if="beranda" class="card">
            <span class="text-sm text-muted">Saldo tagihan</span>
            <strong class="text-4xl">{{ rp(beranda.saldo_tagihan) }}</strong>
            <span class="text-sm text-muted">Sedang diajukan: {{ rp(beranda.sedang_diajukan) }}</span>
        </div>

        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
        <p v-if="info" class="rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm">{{ info }}</p>

        <button class="btn btn-primary" :disabled="proses" @click="ajukan">Ajukan pencairan seluruh tagihan</button>
        <p class="text-center text-xs text-muted">Pencairan mencakup semua transaksi yang belum dicairkan.</p>

        <h2 class="mt-2 font-bold">Riwayat pencairan</h2>
        <div v-for="p in daftar" :key="p.id" class="card flex-row items-center justify-between !p-4">
            <div>
                <div class="font-semibold">{{ rp(p.nominal) }}</div>
                <div class="text-sm text-muted">{{ tanggal(p.created_at) }}</div>
            </div>
            <span class="text-sm font-semibold" :class="warna[p.status]">{{ label[p.status] }}</span>
        </div>
        <p v-if="!daftar.length" class="text-center text-muted">Belum ada pengajuan.</p>
    </Halaman>
</template>
