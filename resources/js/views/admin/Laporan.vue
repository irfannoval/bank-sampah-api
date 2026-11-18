<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import { api } from '../../lib/api';
import { rp } from '../../lib/format';

const jenis = ref('setoran');
const dari = ref('');
const sampai = ref('');
const data = ref(null);
const galat = ref('');
const muat = ref(false);

const tab = [['setoran', 'Setoran per jenis'], ['transaksi-mitra', 'Transaksi per toko'], ['saldo-beredar', 'Kewajiban & saldo']];

async function ambil() {
    muat.value = true;
    galat.value = '';
    try {
        const q = new URLSearchParams();
        if (dari.value) q.set('dari', dari.value);
        if (sampai.value) q.set('sampai', sampai.value);
        data.value = await api.get(`/admin/laporan/${jenis.value}?${q}`);
    } catch (e) {
        galat.value = e.message;
    } finally {
        muat.value = false;
    }
}
function pilih(j) {
    jenis.value = j;
    data.value = null;
    ambil();
}
onMounted(ambil);
</script>

<template>
    <AdminLayout judul="Laporan">
        <div class="mb-4 flex flex-wrap gap-2">
            <button v-for="[v, t] in tab" :key="v" class="btn !min-h-10" :class="jenis === v ? 'btn-primary' : 'btn-ghost'" @click="pilih(v)">{{ t }}</button>
        </div>
        <div v-if="jenis !== 'saldo-beredar'" class="mb-4 flex flex-wrap items-end gap-3">
            <div><label class="label" for="l-dari">Dari tanggal</label><input id="l-dari" v-model="dari" type="date" class="input !w-auto"></div>
            <div><label class="label" for="l-sampai">Sampai tanggal</label><input id="l-sampai" v-model="sampai" type="date" class="input !w-auto"></div>
            <button class="btn btn-ghost" @click="ambil">Terapkan</button>
        </div>
        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
        <p v-if="muat" class="text-muted">Memuat...</p>

        <div v-if="data && jenis === 'setoran'" class="card !gap-0 !p-0" data-testid="laporan-setoran">
            <table class="w-full text-left"><thead><tr class="border-b border-line text-sm text-muted"><th class="p-3">Jenis sampah</th><th class="p-3 text-right">Total berat</th><th class="p-3 text-right">Total nilai</th></tr></thead>
                <tbody>
                    <tr v-for="r in data.per_jenis" :key="r.nama" class="border-b border-line last:border-0"><td class="p-3">{{ r.nama }}</td><td class="p-3 text-right">{{ Number(r.total_berat).toLocaleString('id-ID') }} kg</td><td class="p-3 text-right">{{ rp(r.total_nilai) }}</td></tr>
                    <tr v-if="!data.per_jenis.length"><td colspan="3" class="p-6 text-center text-muted">Tidak ada data pada periode ini.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="data && jenis === 'transaksi-mitra'" class="card !gap-0 !p-0">
            <table class="w-full text-left"><thead><tr class="border-b border-line text-sm text-muted"><th class="p-3">Toko</th><th class="p-3 text-right">Transaksi</th><th class="p-3 text-right">Total</th></tr></thead>
                <tbody>
                    <tr v-for="r in data.per_toko" :key="r.nama_toko" class="border-b border-line last:border-0"><td class="p-3">{{ r.nama_toko }}</td><td class="p-3 text-right">{{ r.jumlah }}</td><td class="p-3 text-right">{{ rp(r.total) }}</td></tr>
                    <tr v-if="!data.per_toko.length"><td colspan="3" class="p-6 text-center text-muted">Tidak ada data pada periode ini.</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="data && jenis === 'saldo-beredar'" class="card" data-testid="laporan-kewajiban">
            <div class="flex justify-between"><span>Saldo seluruh nasabah</span><strong>{{ rp(data.saldo_nasabah) }}</strong></div>
            <div class="flex justify-between"><span>Tagihan toko mitra belum dibayar</span><strong>{{ rp(data.tagihan_mitra_belum_dibayar) }}</strong></div>
            <div class="flex justify-between"><span>Penarikan menunggu</span><strong>{{ rp(data.penarikan_pending) }}</strong></div>
            <div class="flex justify-between border-t border-line pt-2 text-xl"><span>Total kewajiban</span><strong>{{ rp(data.total_kewajiban) }}</strong></div>
            <p class="text-sm text-muted">{{ data.catatan }}</p>
        </div>
    </AdminLayout>
</template>
