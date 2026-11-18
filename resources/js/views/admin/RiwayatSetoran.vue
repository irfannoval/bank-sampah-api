<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import Modal from '../../components/Modal.vue';
import { api } from '../../lib/api';
import { auth } from '../../lib/auth';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/admin/setoran');
const dari = ref('');
const sampai = ref('');
const muat = () => daftar.cari({ dari: dari.value, sampai: sampai.value });
onMounted(muat);

const utama = computed(() => auth.user?.role === 'admin_utama');
const batal = ref(null);
const galat = ref('');
const info = ref('');
const proses = ref(false);

async function kirim() {
    proses.value = true;
    galat.value = '';
    try {
        await api.post(`/admin/setoran/${batal.value.s.id}/batal`, { alasan: batal.value.alasan.trim() });
        batal.value = null;
        info.value = 'Setoran dibatalkan. Saldo nasabah dikurangi lewat mutasi koreksi.';
        muat();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <AdminLayout judul="Riwayat Setoran">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div><label class="label" for="f-dari">Dari tanggal</label><input id="f-dari" v-model="dari" type="date" class="input !w-auto"></div>
            <div><label class="label" for="f-sampai">Sampai tanggal</label><input id="f-sampai" v-model="sampai" type="date" class="input !w-auto"></div>
            <button class="btn btn-ghost" @click="muat">Terapkan</button>
        </div>
        <p v-if="daftar.galat.value" class="pesan-error mb-3" role="alert">{{ daftar.galat.value }}</p>
        <p v-if="info" class="mb-3 rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm" role="status">{{ info }}</p>

        <div class="flex flex-col gap-2">
            <div v-for="s in daftar.items.value" :key="s.id" class="card !flex-row items-center justify-between !p-4">
                <div>
                    <strong>{{ s.nama }}</strong> <span class="text-sm text-muted">{{ s.kode_nasabah }}</span>
                    <div class="text-sm text-muted">{{ tanggal(s.created_at) }} · {{ Number(s.total_berat) }} kg</div>
                    <Badge :teks="s.status" :warna="s.status === 'berhasil' ? 'hijau' : 'merah'" />
                </div>
                <div class="text-right">
                    <div class="font-bold">{{ rp(s.total_nilai) }}</div>
                    <button v-if="utama && s.status === 'berhasil'" class="mt-1 min-h-9 text-sm text-red-700 underline" @click="batal = { s, alasan: '' }; galat = ''">Batalkan</button>
                </div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-6 text-center text-muted">Belum ada setoran.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>

        <Modal v-if="batal" judul="Batalkan setoran" @tutup="batal = null">
            <p>Setoran {{ rp(batal.s.total_nilai) }} milik {{ batal.s.nama }}. Saldo nasabah akan dikurangi. Jika saldonya sudah terpakai, pembatalan ditolak dan perlu koreksi saldo manual.</p>
            <div><label class="label" for="bs-alasan">Alasan (wajib)</label><input id="bs-alasan" v-model="batal.alasan" class="input"></div>
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <button class="btn btn-danger" :disabled="proses || batal.alasan.trim().length < 5" @click="kirim">Batalkan setoran</button>
        </Modal>
    </AdminLayout>
</template>
