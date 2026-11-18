<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import Modal from '../../components/Modal.vue';
import { api } from '../../lib/api';
import { rp } from '../../lib/format';

const daftar = ref([]);
const galat = ref('');
const form = ref(null);
const galatForm = ref('');
const proses = ref(false);

async function muat() {
    try {
        daftar.value = await api.get('/admin/jenis-sampah');
    } catch (e) {
        galat.value = e.message;
    }
}
onMounted(muat);

function buka(j = null) {
    form.value = j
        ? { id: j.id, nama: j.nama, kategori: j.kategori || '', harga: String(Math.round(Number(j.harga_per_kg))), aktif: j.aktif }
        : { id: null, nama: '', kategori: '', harga: '', aktif: true };
    galatForm.value = '';
}

async function simpan() {
    proses.value = true;
    galatForm.value = '';
    const body = { nama: form.value.nama.trim(), kategori: form.value.kategori || null, harga_per_kg: form.value.harga.replace(/\D/g, '') };
    try {
        if (form.value.id) await api.patch(`/admin/jenis-sampah/${form.value.id}`, { ...body, aktif: form.value.aktif });
        else await api.post('/admin/jenis-sampah', body);
        form.value = null;
        await muat();
    } catch (e) {
        galatForm.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <AdminLayout judul="Jenis Sampah & Harga">
        <template #aksi><button class="btn btn-primary" @click="buka()">+ Tambah jenis</button></template>
        <p v-if="galat" class="pesan-error mb-3" role="alert">{{ galat }}</p>
        <p class="mb-3 text-sm text-muted">Mengubah harga tidak mengubah setoran yang sudah tercatat. Harga baru berlaku untuk setoran berikutnya.</p>

        <div class="flex flex-col gap-2">
            <div v-for="j in daftar" :key="j.id" class="card !flex-row items-center justify-between !p-4">
                <div>
                    <strong>{{ j.nama }}</strong> <Badge v-if="!j.aktif" teks="nonaktif" warna="abu" />
                    <div class="text-sm text-muted">{{ j.kategori || 'Tanpa kategori' }}</div>
                </div>
                <div class="flex items-center gap-4">
                    <strong>{{ rp(j.harga_per_kg) }}/kg</strong>
                    <button class="btn btn-ghost !min-h-10" @click="buka(j)">Ubah</button>
                </div>
            </div>
        </div>
        <p v-if="!daftar.length" class="py-6 text-center text-muted">Belum ada jenis sampah. Tambahkan dulu sebelum menerima setoran.</p>

        <Modal v-if="form" :judul="form.id ? 'Ubah jenis sampah' : 'Tambah jenis sampah'" @tutup="form = null">
            <form class="flex flex-col gap-3" @submit.prevent="simpan">
                <div><label class="label" for="j-nama">Nama</label><input id="j-nama" v-model="form.nama" class="input" required></div>
                <div><label class="label" for="j-kat">Kategori (opsional)</label><input id="j-kat" v-model="form.kategori" class="input"></div>
                <div><label class="label" for="j-harga">Harga per kg (Rp)</label><input id="j-harga" v-model="form.harga" class="input" inputmode="numeric" required></div>
                <label v-if="form.id" class="flex min-h-11 items-center gap-2"><input v-model="form.aktif" type="checkbox" class="h-5 w-5"> Aktif (tampil di input setoran)</label>
                <p v-if="galatForm" class="pesan-error" role="alert">{{ galatForm }}</p>
                <button class="btn btn-primary" :disabled="proses || !form.nama.trim() || !form.harga">Simpan</button>
            </form>
        </Modal>
    </AdminLayout>
</template>
