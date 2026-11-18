<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import Modal from '../../components/Modal.vue';
import { api } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp } from '../../lib/format';

const daftar = usePaged('/admin/mitra');
const banks = ref([]);
onMounted(async () => {
    try {
        banks.value = await api.get('/gateway/banks');
    } catch (e) {
        daftar.galat.value = e.message;
    }
    await daftar.cari();
});

const form = ref(null);
const galat = ref('');
const proses = ref(false);
const pinBaru = ref(null);

const kosong = () => ({ id: null, nama: '', no_hp: '', nama_toko: '', alamat: '', bank_tujuan: '', no_rekening: '', atas_nama: '', status: 'aktif' });
function buka(m = null) {
    form.value = m ? { ...kosong(), ...m, alamat: m.alamat || '', bank_tujuan: (m.bank_tujuan || '').toLowerCase(), no_rekening: m.no_rekening || '', atas_nama: m.atas_nama || '' } : kosong();
    galat.value = '';
}

async function simpan() {
    proses.value = true;
    galat.value = '';
    const f = form.value;
    const toko = { nama_toko: f.nama_toko.trim(), alamat: f.alamat || null, bank_tujuan: f.bank_tujuan || null, no_rekening: f.no_rekening || null, atas_nama: f.atas_nama || null };
    try {
        if (f.id) {
            await api.patch(`/admin/mitra/${f.id}`, { ...toko, status: f.status });
        } else {
            const r = await api.post('/admin/mitra', { ...toko, nama: f.nama.trim(), no_hp: f.no_hp.trim() });
            pinBaru.value = { judul: f.nama_toko, pin: r.pin_awal, hp: f.no_hp };
        }
        form.value = null;
        daftar.cari();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}

async function resetPin(m) {
    if (!confirm(`Reset PIN kasir ${m.nama_toko}?`)) return;
    try {
        const r = await api.post(`/admin/mitra/${m.id}/reset-pin`);
        pinBaru.value = { judul: m.nama_toko, pin: r.pin_baru, hp: m.no_hp };
    } catch (e) {
        daftar.galat.value = e.message;
    }
}
</script>

<template>
    <AdminLayout judul="Toko Mitra">
        <template #aksi><button class="btn btn-primary" @click="buka()">+ Tambah toko mitra</button></template>
        <p v-if="daftar.galat.value" class="pesan-error mb-3" role="alert">{{ daftar.galat.value }}</p>

        <div class="flex flex-col gap-2">
            <div v-for="m in daftar.items.value" :key="m.id" class="card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <strong class="text-lg">{{ m.nama_toko }}</strong> <Badge :teks="m.status" :warna="m.status === 'aktif' ? 'hijau' : 'merah'" />
                        <div class="text-sm text-muted">Kasir: {{ m.nama }} · {{ m.no_hp }}</div>
                        <div class="text-sm text-muted">Rekening: {{ m.bank_tujuan ? `${m.bank_tujuan} ${m.no_rekening} a.n. ${m.atas_nama}` : 'belum diisi' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted">Saldo tagihan</div>
                        <strong>{{ rp(m.saldo_tagihan) }}</strong>
                    </div>
                </div>
                <div class="mt-2 flex gap-2">
                    <button class="btn btn-ghost !min-h-10" @click="buka(m)">Ubah</button>
                    <button class="btn btn-ghost !min-h-10" @click="resetPin(m)">Reset PIN kasir</button>
                </div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-6 text-center text-muted">Belum ada toko mitra.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>

        <Modal v-if="form" :judul="form.id ? 'Ubah toko mitra' : 'Tambah toko mitra'" @tutup="form = null">
            <form class="flex flex-col gap-3" @submit.prevent="simpan">
                <div><label class="label" for="m-toko">Nama toko</label><input id="m-toko" v-model="form.nama_toko" class="input" required></div>
                <template v-if="!form.id">
                    <div><label class="label" for="m-nama">Nama kasir/pemilik</label><input id="m-nama" v-model="form.nama" class="input" required></div>
                    <div><label class="label" for="m-hp">Nomor HP (untuk login)</label><input id="m-hp" v-model="form.no_hp" class="input" type="tel" inputmode="numeric" required></div>
                </template>
                <div><label class="label" for="m-alamat">Alamat</label><input id="m-alamat" v-model="form.alamat" class="input"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="m-bank">Bank tujuan</label>
                        <select id="m-bank" v-model="form.bank_tujuan" class="input" :disabled="!banks.length">
                            <option value="">Pilih bank</option>
                            <option v-for="bank in banks" :key="bank.code" :value="bank.code">{{ bank.name }}</option>
                        </select>
                    </div>
                    <div><label class="label" for="m-rek">No. rekening</label><input id="m-rek" v-model="form.no_rekening" class="input" inputmode="numeric"></div>
                </div>
                <div><label class="label" for="m-an">Atas nama rekening</label><input id="m-an" v-model="form.atas_nama" class="input"></div>
                <div v-if="form.id"><label class="label" for="m-status">Status</label><select id="m-status" v-model="form.status" class="input"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></div>
                <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
                <button class="btn btn-primary" :disabled="proses || !form.nama_toko.trim() || (!form.id && (!form.nama.trim() || !form.no_hp.trim()))">Simpan</button>
            </form>
        </Modal>

        <Modal v-if="pinBaru" :judul="`PIN kasir ${pinBaru.judul}`" @tutup="pinBaru = null">
            <p>Login kasir: nomor HP <strong>{{ pinBaru.hp }}</strong></p>
            <div class="rounded-lg border-2 border-amber-400 bg-amber-50 p-4 text-center">
                <div class="text-sm">PIN (catat, hanya tampil sekali)</div>
                <div class="text-4xl font-bold tracking-[0.3em]" data-testid="pin-mitra">{{ pinBaru.pin }}</div>
            </div>
            <button class="btn btn-primary" @click="pinBaru = null">Sudah dicatat</button>
        </Modal>
    </AdminLayout>
</template>
