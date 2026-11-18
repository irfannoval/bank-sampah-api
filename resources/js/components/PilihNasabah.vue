<script setup>
import { nextTick, onMounted, ref } from 'vue';
import QrScanner from './QrScanner.vue';
import { api } from '../lib/api';
import { rp } from '../lib/format';

defineProps({ nasabah: Object, tanpaKartu: Boolean });
const emit = defineEmits(['pilih', 'ganti']);

const kolom = ref('');
const kolomEl = ref(null);
const kamera = ref(false);
const hasil = ref([]);
const galat = ref('');
const mencari = ref(false);
const TOKEN = /^[a-f0-9]{32,64}$/i;

onMounted(() => kolomEl.value?.focus());

async function cari() {
    const teks = kolom.value.trim();
    if (!teks) return;
    galat.value = '';
    hasil.value = [];
    mencari.value = true;
    try {
        if (TOKEN.test(teks)) {
            const r = await api.post('/admin/kartu/scan', { qr_token: teks });
            emit('pilih', { nasabah: r.nasabah, kartuId: r.kartu_id });
        } else {
            const r = await api.get('/admin/nasabah?q=' + encodeURIComponent(teks));
            hasil.value = r.data.filter((n) => n.status === 'aktif');
            if (!hasil.value.length) galat.value = 'Nasabah tidak ditemukan.';
        }
    } catch (e) {
        galat.value = e.message;
    } finally {
        mencari.value = false;
        kolom.value = '';
        kolomEl.value?.focus();
    }
}

function dariKamera(teks) {
    kamera.value = false;
    kolom.value = teks;
    cari();
}

function pilihManual(n) {
    hasil.value = [];
    emit('pilih', { nasabah: n, kartuId: null });
}

function ganti() {
    emit('ganti');
    nextTick(() => kolomEl.value?.focus());
}
</script>

<template>
    <div>
        <template v-if="!nasabah">
            <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="cari">
                <div class="flex-1">
                    <label class="label" for="cari">Scan kartu (scanner/kamera) atau ketik nama / kode nasabah</label>
                    <input id="cari" ref="kolomEl" v-model="kolom" class="input" autocomplete="off" autocapitalize="off" placeholder="Arahkan scanner ke kartu, atau ketik nama">
                </div>
                <button class="btn btn-primary" :disabled="mencari || !kolom.trim()">Cari</button>
                <button type="button" class="btn btn-ghost" @click="kamera = !kamera">{{ kamera ? 'Tutup kamera' : 'Pakai kamera' }}</button>
            </form>
            <div v-if="kamera" class="mt-3"><QrScanner @terbaca="dariKamera" /></div>
            <p v-if="galat" class="pesan-error mt-3" role="alert">{{ galat }}</p>
            <ul v-if="hasil.length" class="mt-3 divide-y divide-line rounded-lg border border-line">
                <li v-for="n in hasil" :key="n.id">
                    <button class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50" @click="pilihManual(n)">
                        <span><strong>{{ n.nama }}</strong> <span class="text-sm text-muted">{{ n.kode_nasabah }}</span></span>
                        <span class="text-sm text-muted">Saldo {{ rp(n.saldo) }}</span>
                    </button>
                </li>
            </ul>
        </template>
        <div v-else class="flex items-center justify-between gap-3 rounded-lg bg-brand-50 px-4 py-3" data-testid="nasabah-terpilih">
            <div>
                <strong class="text-lg">{{ nasabah.nama }}</strong>
                <span class="ml-2 text-sm text-muted">{{ nasabah.kode_nasabah }}</span>
                <div class="text-sm text-muted">Saldo {{ rp(nasabah.saldo) }}<span v-if="tanpaKartu"> · dicari manual (tanpa kartu)</span></div>
            </div>
            <button class="btn btn-ghost !min-h-10" @click="ganti">Ganti</button>
        </div>
    </div>
</template>
