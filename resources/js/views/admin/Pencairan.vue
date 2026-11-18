<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import Modal from '../../components/Modal.vue';
import { api, unduh } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/admin/pencairan');
const status = ref('');
const galat = ref('');
const info = ref('');
const proses = ref(false);
const tolak = ref(null);
const bayar = ref(null);

const tab = [['', 'Semua'], ['pending', 'Menunggu'], ['disetujui', 'Disetujui'], ['dibayar', 'Dibayar'], ['ditolak', 'Ditolak']];
const warna = { pending: 'kuning', disetujui: 'biru', dibayar: 'hijau', ditolak: 'merah' };
const label = { pending: 'Menunggu', disetujui: 'Disetujui', dibayar: 'Dibayar', ditolak: 'Ditolak' };

const muat = () => daftar.cari({ status: status.value });
onMounted(muat);

async function jalankan(fn, pesan) {
    proses.value = true;
    galat.value = '';
    info.value = '';
    try {
        await fn();
        info.value = pesan;
        tolak.value = null;
        bayar.value = null;
        await muat();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}

const setujui = (p) => jalankan(() => api.post(`/admin/pencairan/${p.id}/setujui`), 'Pengajuan disetujui.');
const kirimTolak = () => jalankan(() => api.post(`/admin/pencairan/${tolak.value.p.id}/tolak`, { alasan: tolak.value.alasan.trim() }), 'Pengajuan ditolak. Transaksi bisa diajukan ulang.');
function kirimBayar() {
    const f = new FormData();
    f.append('bukti_transfer', bayar.value.berkas);
    return jalankan(() => api.post(`/admin/pencairan/${bayar.value.p.id}/bayar`, f), 'Pencairan ditandai dibayar.');
}

async function lihatBukti(p) {
    try {
        const url = URL.createObjectURL(await unduh(`/admin/pencairan/${p.id}/bukti`));
        window.open(url, '_blank');
    } catch (e) {
        galat.value = e.message;
    }
}
</script>

<template>
    <AdminLayout judul="Pencairan Mitra">
        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="[v, t] in tab" :key="v" class="btn !min-h-10" :class="status === v ? 'btn-primary' : 'btn-ghost'" @click="status = v; muat()">{{ t }}</button>
        </div>
        <p v-if="galat || daftar.galat.value" class="pesan-error mb-3" role="alert">{{ galat || daftar.galat.value }}</p>
        <p v-if="info" class="mb-3 rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm" role="status">{{ info }}</p>

        <div class="flex flex-col gap-3">
            <div v-for="p in daftar.items.value" :key="p.id" class="card" data-testid="pencairan">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <strong class="text-lg">{{ p.nama_toko }}</strong>
                        <div class="text-sm text-muted">Diajukan {{ tanggal(p.created_at) }}</div>
                        <div class="text-sm">Rekening: {{ p.bank_tujuan }} {{ p.no_rekening }} a.n. {{ p.atas_nama }}</div>
                    </div>
                    <div class="text-right"><div class="text-2xl font-bold">{{ rp(p.nominal) }}</div><Badge :teks="label[p.status]" :warna="warna[p.status]" /></div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button v-if="p.status === 'pending'" class="btn btn-primary !min-h-10" :disabled="proses" @click="setujui(p)">Setujui</button>
                    <button v-if="p.status === 'disetujui'" class="btn btn-primary !min-h-10" @click="bayar = { p, berkas: null }">Tandai dibayar</button>
                    <button v-if="['pending', 'disetujui'].includes(p.status)" class="btn btn-danger !min-h-10" @click="tolak = { p, alasan: '' }">Tolak</button>
                    <button v-if="p.status === 'dibayar'" class="btn btn-ghost !min-h-10" @click="lihatBukti(p)">Lihat bukti transfer</button>
                </div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-6 text-center text-muted">Tidak ada pengajuan.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>

        <Modal v-if="tolak" judul="Tolak pengajuan" @tutup="tolak = null">
            <p>Pengajuan {{ rp(tolak.p.nominal) }} dari {{ tolak.p.nama_toko }}. Transaksinya bisa diajukan ulang oleh mitra.</p>
            <div><label class="label" for="t-alasan">Alasan</label><input id="t-alasan" v-model="tolak.alasan" class="input"></div>
            <button class="btn btn-danger" :disabled="proses || tolak.alasan.trim().length < 3" @click="kirimTolak">Tolak pengajuan</button>
        </Modal>

        <Modal v-if="bayar" judul="Tandai dibayar" @tutup="bayar = null">
            <p>Transfer <strong>{{ rp(bayar.p.nominal) }}</strong> ke {{ bayar.p.bank_tujuan }} {{ bayar.p.no_rekening }} a.n. {{ bayar.p.atas_nama }}, lalu unggah bukti transfernya.</p>
            <div><label class="label" for="b-berkas">Bukti transfer (JPG, PNG, atau PDF, maks 4 MB)</label><input id="b-berkas" type="file" accept=".jpg,.jpeg,.png,.pdf" class="input !py-2" @change="bayar.berkas = $event.target.files[0]"></div>
            <button class="btn btn-primary" :disabled="proses || !bayar.berkas" @click="kirimBayar">Konfirmasi sudah dibayar</button>
        </Modal>
    </AdminLayout>
</template>
