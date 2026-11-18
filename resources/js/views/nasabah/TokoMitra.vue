<script setup>
import { onMounted, ref } from 'vue';
import Halaman from '../../components/Halaman.vue';
import { api } from '../../lib/api';

const daftar = ref([]);
const galat = ref('');
const selesai = ref(false);
onMounted(async () => {
    try {
        daftar.value = await api.get('/nasabah/toko-mitra');
    } catch (e) {
        galat.value = e.message;
    } finally {
        selesai.value = true;
    }
});
</script>

<template>
    <Halaman judul="Toko Mitra" kembali="/nasabah">
        <p class="text-muted">Saldo kamu bisa dipakai belanja di toko-toko ini. Cukup tunjukkan kartu.</p>
        <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
        <div v-for="t in daftar" :key="t.id" class="card"><strong>{{ t.nama_toko }}</strong><span class="text-sm text-muted">{{ t.alamat || 'Alamat belum diisi' }}</span></div>
        <p v-if="selesai && !daftar.length && !galat" class="text-center text-muted">Belum ada toko mitra.</p>
    </Halaman>
</template>
