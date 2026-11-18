<script setup>
import { onMounted } from 'vue';
import Halaman from '../../components/Halaman.vue';
import Badge from '../../components/Badge.vue';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/nasabah/penarikan');
onMounted(() => daftar.cari());
const label = { pending: 'Menunggu Midtrans', berhasil: 'Berhasil', gagal: 'Gagal (saldo dikembalikan)' };
const warna = { pending: 'kuning', berhasil: 'hijau', gagal: 'merah' };
</script>

<template>
    <Halaman judul="Riwayat Penarikan" kembali="/nasabah">
        <p v-if="daftar.galat.value" class="pesan-error" role="alert">{{ daftar.galat.value }}</p>
        <div v-for="p in daftar.items.value" :key="p.id" class="card !flex-row items-center justify-between !p-4">
            <div>
                <div class="font-semibold">Rekening {{ p.tujuan_bank }} {{ p.tujuan_nomor }}</div>
                <div class="text-sm text-muted">{{ tanggal(p.created_at) }}</div>
                <div v-if="p.ref_gateway" class="text-xs text-muted">Referensi Midtrans: {{ p.ref_gateway }}</div>
                <div v-else-if="p.status === 'pending' && !p.tujuan_bank" class="text-xs text-amber-700">Data rekening payout lama tidak lengkap. Hubungi admin untuk rekonsiliasi.</div>
                <div v-else-if="p.status === 'pending'" class="text-xs text-amber-700">Menunggu pengiriman ulang ke gateway.</div>
                <Badge :teks="label[p.status]" :warna="warna[p.status]" />
            </div>
            <div class="text-right">
                <div class="font-bold">{{ rp(p.nominal_diterima) }}</div>
                <div class="text-xs text-muted">dari {{ rp(p.nominal) }}</div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="text-center text-muted">Belum ada penarikan.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost" @click="daftar.muatLagi()">Muat lebih banyak</button>
    </Halaman>
</template>
