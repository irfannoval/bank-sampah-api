<script setup>
import { onMounted } from 'vue';
import Halaman from '../../components/Halaman.vue';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/nasabah/mutasi');
onMounted(() => daftar.cari());
const tipe = { setoran: 'Setoran sampah', belanja: 'Belanja di toko mitra', penarikan: 'Penarikan saldo', refund: 'Pengembalian saldo', koreksi: 'Koreksi saldo' };
</script>

<template>
    <Halaman judul="Riwayat Saldo" kembali="/nasabah">
        <p v-if="daftar.galat.value" class="pesan-error" role="alert">{{ daftar.galat.value }}</p>
        <div v-for="m in daftar.items.value" :key="m.id" class="card !flex-row items-center justify-between !p-4">
            <div>
                <div class="font-semibold">{{ tipe[m.tipe] || m.tipe }}</div>
                <div class="text-sm text-muted">{{ tanggal(m.created_at) }}</div>
            </div>
            <div class="text-right">
                <div class="font-bold" :class="m.arah === 'kredit' ? 'text-brand-600' : 'text-red-700'">{{ m.arah === 'kredit' ? '+' : '-' }}{{ rp(m.nominal) }}</div>
                <div class="text-xs text-muted">Saldo {{ rp(m.saldo_sesudah) }}</div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="text-center text-muted">Belum ada riwayat.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost" @click="daftar.muatLagi()">Muat lebih banyak</button>
    </Halaman>
</template>
