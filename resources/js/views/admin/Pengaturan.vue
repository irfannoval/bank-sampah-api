<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import { api } from '../../lib/api';

const baris = ref([]);
const asli = ref({});
const galat = ref('');
const info = ref('');
const proses = ref(false);

const nama = {
    penarikan_min: 'Minimal penarikan (Rp)',
    penarikan_maks: 'Maksimal penarikan per transaksi (Rp)',
    penarikan_maks_per_hari: 'Maksimal jumlah penarikan per hari (kali)',
    penarikan_total_per_hari: 'Maksimal total penarikan per hari (Rp)',
    penarikan_biaya_admin: 'Biaya admin penarikan (Rp)',
    saldo_min_tersisa: 'Saldo minimal yang tersisa setelah penarikan (Rp)',
    batas_batal_jam: 'Batas waktu pembatalan transaksi mitra (jam)',
    pin_maks_gagal: 'Salah PIN berturut-turut sebelum akun dikunci',
    pin_kunci_menit: 'Lama akun dikunci (menit)',
};

async function muat() {
    try {
        baris.value = await api.get('/admin/pengaturan');
        asli.value = Object.fromEntries(baris.value.map((b) => [b.kunci, b.nilai]));
    } catch (e) {
        galat.value = e.message;
    }
}
onMounted(muat);

async function simpan() {
    const berubah = Object.fromEntries(baris.value.filter((b) => b.nilai !== asli.value[b.kunci]).map((b) => [b.kunci, b.nilai]));
    if (!Object.keys(berubah).length) {
        info.value = 'Tidak ada perubahan.';
        return;
    }
    proses.value = true;
    galat.value = '';
    info.value = '';
    try {
        await api.patch('/admin/pengaturan', { pengaturan: berubah });
        info.value = 'Pengaturan disimpan dan tercatat di audit log.';
        await muat();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <AdminLayout judul="Pengaturan Sistem">
        <p v-if="galat" class="pesan-error mb-3" role="alert">{{ galat }}</p>
        <p v-if="info" class="mb-3 rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm" role="status">{{ info }}</p>
        <form class="card !gap-4" @submit.prevent="simpan">
            <div v-for="b in baris" :key="b.kunci">
                <label class="label" :for="`s-${b.kunci}`">{{ nama[b.kunci] || b.kunci }}</label>
                <input :id="`s-${b.kunci}`" v-model="b.nilai" class="input max-w-xs" inputmode="numeric">
                <p v-if="b.keterangan && b.keterangan.includes('CONTOH')" class="mt-1 text-xs text-amber-700">{{ b.keterangan }}</p>
            </div>
            <button class="btn btn-primary sm:self-start" :disabled="proses">Simpan perubahan</button>
        </form>
    </AdminLayout>
</template>
