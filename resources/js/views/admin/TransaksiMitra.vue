<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import Modal from '../../components/Modal.vue';
import { api } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/admin/transaksi-mitra');
onMounted(() => daftar.cari());
const batal = ref(null);
const galat = ref('');
const info = ref('');
const proses = ref(false);

async function kirim() {
    proses.value = true;
    galat.value = '';
    try {
        await api.post(`/admin/transaksi-mitra/${batal.value.t.id}/batal`, { alasan: batal.value.alasan.trim() });
        batal.value = null;
        info.value = 'Transaksi dibatalkan dan saldo nasabah dikembalikan.';
        daftar.cari();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <AdminLayout judul="Transaksi Toko Mitra">
        <p v-if="daftar.galat.value" class="pesan-error mb-3" role="alert">{{ daftar.galat.value }}</p>
        <p v-if="info" class="mb-3 rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm" role="status">{{ info }}</p>
        <div class="flex flex-col gap-2">
            <div v-for="t in daftar.items.value" :key="t.id" class="card !flex-row items-center justify-between !p-4">
                <div>
                    <strong>{{ t.nama_toko }}</strong>
                    <div class="text-sm text-muted">{{ t.nama }} ({{ t.kode_nasabah }}) · {{ tanggal(t.created_at) }}</div>
                    <div class="mt-1 flex gap-2">
                        <Badge :teks="t.status" :warna="t.status === 'berhasil' ? 'hijau' : 'merah'" />
                        <Badge v-if="t.pencairan_id" teks="sudah masuk pencairan" warna="biru" />
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold">{{ rp(t.nominal) }}</div>
                    <button v-if="t.status === 'berhasil'" class="mt-1 min-h-9 text-sm text-red-700 underline" @click="batal = { t, alasan: '' }; galat = ''">Batalkan</button>
                </div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-6 text-center text-muted">Belum ada transaksi.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>

        <Modal v-if="batal" judul="Batalkan transaksi" @tutup="batal = null">
            <p>{{ rp(batal.t.nominal) }} di {{ batal.t.nama_toko }}. Saldo nasabah dikembalikan otomatis. Batas pembatalan 24 jam; yang sudah masuk pencairan hanya bisa dibatalkan admin utama.</p>
            <div><label class="label" for="bt-alasan">Alasan</label><input id="bt-alasan" v-model="batal.alasan" class="input"></div>
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <button class="btn btn-danger" :disabled="proses || batal.alasan.trim().length < 3" @click="kirim">Batalkan transaksi</button>
        </Modal>
    </AdminLayout>
</template>
