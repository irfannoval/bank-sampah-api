<script setup>
import { onMounted, ref } from 'vue';
import Halaman from '../../components/Halaman.vue';
import { api } from '../../lib/api';
import { rp, tanggal } from '../../lib/format';

const daftar = ref([]);
const halaman = ref(1);
const terakhir = ref(false);
const galat = ref('');
const muat = ref(false);

const batal = ref(null); // transaksi yang akan dibatalkan
const alasan = ref('');
const proses = ref(false);

async function ambil() {
    muat.value = true;
    try {
        const r = await api.get(`/mitra/transaksi?page=${halaman.value}`);
        daftar.value.push(...r.data);
        terakhir.value = r.current_page >= r.last_page;
        halaman.value++;
    } catch (e) {
        galat.value = e.message;
    } finally {
        muat.value = false;
    }
}
onMounted(ambil);

const label = { berhasil: 'Berhasil', dibatalkan: 'Dibatalkan' };

async function konfirmasiBatal() {
    proses.value = true;
    galat.value = '';
    try {
        await api.post(`/mitra/transaksi/${batal.value.id}/batal`, { alasan: alasan.value.trim() });
        batal.value.status = 'dibatalkan';
        batal.value = null;
        alasan.value = '';
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <Halaman judul="Riwayat Transaksi" kembali="/mitra">
        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>

        <div v-for="t in daftar" :key="t.id" class="card flex-row items-center justify-between gap-3 !p-4">
            <div class="min-w-0">
                <div class="truncate font-semibold">{{ t.nama }}</div>
                <div class="text-sm text-muted">{{ tanggal(t.created_at) }}</div>
                <div class="text-sm" :class="t.status === 'berhasil' ? 'text-brand-600' : 'text-red-700'">
                    {{ label[t.status] }}<span v-if="t.pencairan_id"> · sudah masuk pencairan</span>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <div class="font-bold">{{ rp(t.nominal) }}</div>
                <button v-if="t.status === 'berhasil' && !t.pencairan_id" class="mt-1 min-h-9 text-sm text-red-700 underline" @click="batal = t">Batalkan</button>
            </div>
        </div>

        <p v-if="!muat && !daftar.length && !galat" class="text-center text-muted">Belum ada transaksi.</p>
        <button v-if="!terakhir && daftar.length" class="btn btn-ghost" :disabled="muat" @click="ambil">Muat lebih banyak</button>

        <div v-if="batal" class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 p-4">
            <form class="card w-full max-w-md" @submit.prevent="konfirmasiBatal">
                <strong>Batalkan transaksi {{ rp(batal.nominal) }}?</strong>
                <span class="text-sm text-muted">Saldo nasabah akan dikembalikan otomatis.</span>
                <label class="label mt-2" for="alasan-batal">Alasan</label>
                <input id="alasan-batal" v-model="alasan" class="input" placeholder="Contoh: salah input nominal">
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <button type="button" class="btn btn-ghost" @click="batal = null">Kembali</button>
                    <button class="btn btn-danger" :disabled="proses || alasan.trim().length < 3">Batalkan</button>
                </div>
            </form>
        </div>
    </Halaman>
</template>
