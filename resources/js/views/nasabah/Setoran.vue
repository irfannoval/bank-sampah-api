<script setup>
import { onMounted, ref } from 'vue';
import Halaman from '../../components/Halaman.vue';
import Badge from '../../components/Badge.vue';
import { api } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/nasabah/setoran');
onMounted(() => daftar.cari());

const buka = ref(null);
const rincian = ref({});
const galat = ref('');

async function toggle(s) {
    if (buka.value === s.id) {
        buka.value = null;
        return;
    }
    buka.value = s.id;
    if (!rincian.value[s.id]) {
        try {
            rincian.value[s.id] = (await api.get(`/nasabah/setoran/${s.id}`)).rincian;
        } catch (e) {
            galat.value = e.message;
        }
    }
}
</script>

<template>
    <Halaman judul="Setoran Saya" kembali="/nasabah">
        <p v-if="galat || daftar.galat.value" class="pesan-error" role="alert">{{ galat || daftar.galat.value }}</p>
        <div v-for="s in daftar.items.value" :key="s.id" class="card !p-0">
            <button class="flex w-full items-center justify-between p-4 text-left" @click="toggle(s)">
                <div>
                    <div class="font-semibold">{{ Number(s.total_berat).toLocaleString('id-ID') }} kg</div>
                    <div class="text-sm text-muted">{{ tanggal(s.created_at) }}</div>
                    <Badge v-if="s.status !== 'berhasil'" :teks="s.status" warna="merah" />
                </div>
                <div class="font-bold text-brand-600">+{{ rp(s.total_nilai) }}</div>
            </button>
            <ul v-if="buka === s.id && rincian[s.id]" class="border-t border-line px-4 py-2 text-sm">
                <li v-for="r in rincian[s.id]" :key="r.nama + r.berat" class="flex justify-between py-1">
                    <span>{{ r.nama }} · {{ Number(r.berat) }} kg × {{ rp(r.harga_saat_itu) }}</span><span>{{ rp(r.subtotal) }}</span>
                </li>
            </ul>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="text-center text-muted">Belum ada setoran.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost" @click="daftar.muatLagi()">Muat lebih banyak</button>
    </Halaman>
</template>
