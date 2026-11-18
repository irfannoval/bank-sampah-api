<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../components/Halaman.vue';
import { api } from '../lib/api';
import { auth, rumah } from '../lib/auth';

const router = useRouter();
const lama = ref('');
const baru = ref('');
const ulang = ref('');
const galat = ref('');
const sukses = ref(false);
const proses = ref(false);
const kembali = computed(() => rumah(auth.user?.role));
const valid = computed(() => lama.value.length === 6 && baru.value.length === 6 && baru.value === ulang.value && baru.value !== lama.value);

async function simpan() {
    proses.value = true;
    galat.value = '';
    try {
        await api.post('/auth/ubah-pin', { pin_lama: lama.value, pin_baru: baru.value });
        sukses.value = true;
        lama.value = baru.value = ulang.value = '';
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <Halaman judul="Ubah PIN" :kembali="kembali">
        <p v-if="sukses" class="rounded-lg border border-brand-600 bg-brand-50 px-4 py-3" role="status">PIN berhasil diubah.</p>
        <form class="card !gap-4" @submit.prevent="simpan">
            <div><label class="label" for="pl">PIN lama</label><input id="pl" v-model="lama" class="input" type="password" inputmode="numeric" maxlength="6" autocomplete="current-password"></div>
            <div><label class="label" for="pb">PIN baru (6 digit angka)</label><input id="pb" v-model="baru" class="input" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password"></div>
            <div><label class="label" for="pu">Ulangi PIN baru</label><input id="pu" v-model="ulang" class="input" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password"></div>
            <p v-if="ulang && baru !== ulang" class="text-sm text-red-700">PIN baru dan pengulangannya belum sama.</p>
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <button class="btn btn-primary" :disabled="proses || !valid">Simpan PIN baru</button>
        </form>
        <button class="btn btn-ghost" @click="router.push(kembali)">Kembali</button>
    </Halaman>
</template>
