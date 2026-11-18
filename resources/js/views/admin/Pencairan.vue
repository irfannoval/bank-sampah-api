<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/admin/pencairan');
const status = ref('');
const tab = [['', 'Semua'], ['pending', 'Menunggu Midtrans'], ['dibayar', 'Berhasil'], ['ditolak', 'Gagal']];
const warna = { pending: 'kuning', dibayar: 'hijau', ditolak: 'merah' };
const label = { pending: 'Menunggu persetujuan Midtrans', dibayar: 'Berhasil', ditolak: 'Gagal' };

const muat = () => daftar.cari({ status: status.value });
onMounted(muat);
</script>

<template>
    <AdminLayout judul="Pencairan Mitra">
        <p class="mb-4 rounded-lg border border-amber-400 bg-amber-50 px-4 py-3 text-sm">
            Payout dibuat otomatis saat mitra mengajukan. Approver harus menyetujui payout di Midtrans Iris menggunakan OTP.
        </p>
        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="[v, t] in tab" :key="v" class="btn !min-h-10" :class="status === v ? 'btn-primary' : 'btn-ghost'" @click="status = v; muat()">{{ t }}</button>
        </div>
        <p v-if="daftar.galat.value" class="pesan-error mb-3" role="alert">{{ daftar.galat.value }}</p>

        <div class="flex flex-col gap-3">
            <div v-for="p in daftar.items.value" :key="p.id" class="card" data-testid="pencairan">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <strong class="text-lg">{{ p.nama_toko }}</strong>
                        <div class="text-sm text-muted">Diajukan {{ tanggal(p.created_at) }}</div>
                        <div class="text-sm">Rekening: {{ p.bank_tujuan }} {{ p.no_rekening }} a.n. {{ p.atas_nama }}</div>
                        <div v-if="p.ref_gateway" class="text-xs text-muted">Referensi Midtrans: {{ p.ref_gateway }}</div>
                        <div v-else-if="p.status === 'pending' && !p.bank_tujuan" class="text-xs text-amber-700">Data payout lama tidak memiliki snapshot rekening. Perlu rekonsiliasi admin; tidak dikirim otomatis.</div>
                        <div v-else-if="p.status === 'pending'" class="text-xs text-amber-700">Menunggu pengiriman ulang ke gateway.</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold">{{ rp(p.nominal) }}</div>
                        <Badge :teks="label[p.status] || p.status" :warna="warna[p.status] || 'abu'" />
                    </div>
                </div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-6 text-center text-muted">Tidak ada pengajuan.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>
    </AdminLayout>
</template>
