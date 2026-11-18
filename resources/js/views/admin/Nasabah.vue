<script setup>
import { onMounted, ref, watch } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import KartuPreview from '../../components/KartuPreview.vue';
import Modal from '../../components/Modal.vue';
import { api } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp } from '../../lib/format';

const daftar = usePaged('/admin/nasabah');
const q = ref('');
let timer = null;
watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(() => daftar.cari({ q: q.value.trim() }), 300);
});
onMounted(() => daftar.cari());

// pendaftaran
const form = ref(null);
const galat = ref('');
const proses = ref(false);
const baru = ref(null);

function buka() {
    form.value = { nama: '', no_hp: '', alamat: '' };
    galat.value = '';
}
async function daftarkan() {
    proses.value = true;
    galat.value = '';
    try {
        const r = await api.post('/admin/nasabah', { ...form.value, alamat: form.value.alamat || null });
        baru.value = { ...r, nama: form.value.nama };
        form.value = null;
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
function cetak() {
    window.print();
}
function selesai() {
    baru.value = null;
    daftar.cari({ q: q.value.trim() });
}
</script>

<template>
    <AdminLayout judul="Nasabah">
        <template #aksi><button class="btn btn-primary" @click="buka">+ Daftarkan nasabah</button></template>

        <input v-model="q" class="input mb-4" placeholder="Cari nama, kode, atau nomor HP" aria-label="Cari nasabah">
        <p v-if="daftar.galat.value" class="pesan-error mb-3" role="alert">{{ daftar.galat.value }}</p>

        <div class="flex flex-col gap-2">
            <router-link
                v-for="n in daftar.items.value"
                :key="n.id"
                :to="`/admin/nasabah/${n.id}`"
                class="card !flex-row items-center justify-between !p-4 hover:bg-gray-50"
            >
                <div>
                    <strong>{{ n.nama }}</strong> <span class="text-sm text-muted">{{ n.kode_nasabah }}</span>
                    <div class="text-sm text-muted">{{ n.no_hp }}</div>
                </div>
                <div class="text-right">
                    <div class="font-bold">{{ rp(n.saldo) }}</div>
                    <Badge :teks="n.status" :warna="n.status === 'aktif' ? 'hijau' : 'merah'" />
                </div>
            </router-link>
        </div>
        <p v-if="!daftar.muat.value && !daftar.items.value.length" class="py-6 text-center text-muted">Belum ada nasabah.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" :disabled="daftar.muat.value" @click="daftar.muatLagi()">Muat lebih banyak</button>

        <Modal v-if="form" judul="Daftarkan nasabah" @tutup="form = null">
            <form class="flex flex-col gap-3" @submit.prevent="daftarkan">
                <div><label class="label" for="n-nama">Nama lengkap</label><input id="n-nama" v-model="form.nama" class="input" required></div>
                <div><label class="label" for="n-hp">Nomor HP</label><input id="n-hp" v-model="form.no_hp" class="input" type="tel" inputmode="numeric" required></div>
                <div><label class="label" for="n-alamat">Alamat (opsional)</label><input id="n-alamat" v-model="form.alamat" class="input"></div>
                <p class="text-sm text-muted">PIN awal dibuat otomatis dan ditampilkan sekali setelah pendaftaran. Kartu langsung dibuat.</p>
                <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
                <button class="btn btn-primary" :disabled="proses || !form.nama.trim() || !form.no_hp.trim()">Daftarkan</button>
            </form>
        </Modal>

        <Modal v-if="baru" judul="Nasabah terdaftar" lebar="max-w-xl" @tutup="selesai">
            <p><strong>{{ baru.nama }}</strong> ({{ baru.kode_nasabah }})</p>
            <div class="rounded-lg border-2 border-amber-400 bg-amber-50 p-4 text-center">
                <div class="text-sm">PIN awal nasabah (catat, hanya tampil sekali)</div>
                <div class="text-4xl font-bold tracking-[0.3em]" data-testid="pin-awal">{{ baru.pin_awal }}</div>
            </div>
            <div class="flex justify-center overflow-x-auto py-2">
                <KartuPreview :nama="baru.nama" :kode="baru.kode_nasabah" :token="baru.kartu.qr_token" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <button class="btn btn-ghost" @click="selesai">Selesai</button>
                <button class="btn btn-primary" @click="cetak">Cetak kartu</button>
            </div>
        </Modal>
    </AdminLayout>
</template>
