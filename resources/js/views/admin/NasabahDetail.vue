<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import KartuPreview from '../../components/KartuPreview.vue';
import Modal from '../../components/Modal.vue';
import { api, buatKunci } from '../../lib/api';
import { auth } from '../../lib/auth';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const id = useRoute().params.id;
const data = ref(null);
const galat = ref('');
const info = ref('');
const proses = ref(false);
const mutasi = usePaged(`/admin/nasabah/${id}/mutasi`);

const aktif = computed(() => data.value?.kartu.find((k) => k.status === 'aktif'));
const utama = computed(() => auth.user?.role === 'admin_utama');

async function muat() {
    try {
        data.value = await api.get(`/admin/nasabah/${id}`);
    } catch (e) {
        galat.value = e.message;
    }
}
onMounted(() => {
    muat();
    mutasi.cari();
});

async function aksi(fn, pesan = '') {
    proses.value = true;
    galat.value = '';
    info.value = '';
    try {
        await fn();
        info.value = pesan;
        await muat();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}

const cetakKartu = () => aksi(() => api.post(`/admin/nasabah/${id}/kartu`), 'Kartu baru dibuat.');
const ubahKartu = (k, status) => aksi(() => api.post(`/admin/kartu/${k.id}/status`, { status }), 'Status kartu diperbarui.');
const gantiKartu = (k) => aksi(() => api.post(`/admin/kartu/${k.id}/ganti`, { status_lama: 'hilang' }), 'Kartu lama dinonaktifkan, kartu baru dibuat. Cetak kartu baru.');
const ubahAkun = (status) => aksi(() => api.patch(`/admin/nasabah/${id}`, { status }), 'Status akun diperbarui.');

const pinBaru = ref('');
async function resetPin() {
    if (!confirm('Reset PIN nasabah ini? PIN lama tidak berlaku lagi.')) return;
    await aksi(async () => {
        pinBaru.value = (await api.post(`/admin/nasabah/${id}/reset-pin`)).pin_baru;
    });
}

// koreksi saldo
const koreksi = ref(null);
const galatKoreksi = ref('');
async function simpanKoreksi() {
    galatKoreksi.value = '';
    proses.value = true;
    try {
        await api.post(`/admin/nasabah/${id}/koreksi`, { ...koreksi.value, nominal: koreksi.value.nominal.replace(/\D/g, '') }, { idem: buatKunci() });
        koreksi.value = null;
        info.value = 'Saldo dikoreksi dan tercatat di audit log.';
        await muat();
        mutasi.cari();
    } catch (e) {
        galatKoreksi.value = e.message;
    } finally {
        proses.value = false;
    }
}
function cetak() {
    window.print();
}
const tipe = { setoran: 'Setoran', belanja: 'Belanja', penarikan: 'Penarikan', refund: 'Pengembalian', koreksi: 'Koreksi' };
</script>

<template>
    <AdminLayout :judul="data?.nasabah.nama || 'Nasabah'">
        <template #aksi><router-link to="/admin/nasabah" class="btn btn-ghost">&larr; Daftar nasabah</router-link></template>

        <p v-if="galat" class="pesan-error mb-3" role="alert">{{ galat }}</p>
        <p v-if="info" class="mb-3 rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 text-sm" role="status">{{ info }}</p>

        <template v-if="data">
            <div class="grid gap-4 md:grid-cols-2">
                <section class="card">
                    <h2 class="font-bold">Profil</h2>
                    <div>{{ data.nasabah.kode_nasabah }} · {{ data.nasabah.no_hp }}</div>
                    <div class="text-sm text-muted">{{ data.nasabah.alamat || 'Alamat belum diisi' }}</div>
                    <div class="mt-1 text-3xl font-bold">{{ rp(data.nasabah.saldo) }}</div>
                    <div><Badge :teks="data.nasabah.status" :warna="data.nasabah.status === 'aktif' ? 'hijau' : 'merah'" /></div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button class="btn btn-ghost !min-h-10" :disabled="proses" @click="resetPin">Reset PIN</button>
                        <button v-if="data.nasabah.status === 'aktif'" class="btn btn-danger !min-h-10" :disabled="proses" @click="ubahAkun('nonaktif')">Nonaktifkan</button>
                        <button v-else class="btn btn-ghost !min-h-10" :disabled="proses" @click="ubahAkun('aktif')">Aktifkan</button>
                        <button v-if="utama" class="btn btn-ghost !min-h-10" @click="koreksi = { arah: 'kredit', nominal: '', alasan: '' }; galatKoreksi = ''">Koreksi saldo</button>
                    </div>
                </section>

                <section class="card">
                    <h2 class="font-bold">Kartu</h2>
                    <div v-if="aktif" class="flex flex-col gap-3">
                        <div class="overflow-x-auto"><KartuPreview :nama="data.nasabah.nama" :kode="data.nasabah.kode_nasabah" :token="aktif.qr_token" /></div>
                        <div class="flex flex-wrap gap-2">
                            <button class="btn btn-primary !min-h-10" @click="cetak">Cetak</button>
                            <button class="btn btn-ghost !min-h-10" :disabled="proses" @click="ubahKartu(aktif, 'diblokir')">Blokir</button>
                            <button class="btn btn-danger !min-h-10" :disabled="proses" @click="gantiKartu(aktif)">Hilang / ganti kartu</button>
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <p class="text-muted">Nasabah belum punya kartu aktif.</p>
                        <button class="btn btn-primary" :disabled="proses" @click="cetakKartu">Buat kartu baru</button>
                    </div>
                    <ul v-if="data.kartu.filter((k) => k.status !== 'aktif').length" class="mt-2 text-sm">
                        <li v-for="k in data.kartu.filter((x) => x.status !== 'aktif')" :key="k.id" class="flex items-center justify-between border-t border-line py-2">
                            <span>Kartu #{{ k.id }} <Badge :teks="k.status" :warna="k.status === 'diblokir' ? 'kuning' : 'merah'" /></span>
                            <button v-if="k.status === 'diblokir' && !aktif" class="text-brand-700 underline" @click="ubahKartu(k, 'aktif')">Aktifkan lagi</button>
                        </li>
                    </ul>
                </section>
            </div>

            <h2 class="mb-2 mt-6 text-lg font-bold">Mutasi saldo</h2>
            <div class="flex flex-col gap-2">
                <div v-for="m in mutasi.items.value" :key="m.id" class="card !flex-row items-center justify-between !p-3">
                    <div>
                        <div class="font-semibold">{{ tipe[m.tipe] || m.tipe }}</div>
                        <div class="text-sm text-muted">{{ tanggal(m.created_at) }}<span v-if="m.keterangan"> · {{ m.keterangan }}</span></div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold" :class="m.arah === 'kredit' ? 'text-brand-600' : 'text-red-700'">{{ m.arah === 'kredit' ? '+' : '-' }}{{ rp(m.nominal) }}</div>
                        <div class="text-xs text-muted">Saldo {{ rp(m.saldo_sesudah) }}</div>
                    </div>
                </div>
            </div>
            <p v-if="!mutasi.items.value.length && !mutasi.muat.value" class="py-4 text-center text-muted">Belum ada mutasi.</p>
            <button v-if="!mutasi.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="mutasi.muatLagi()">Muat lebih banyak</button>
        </template>

        <Modal v-if="pinBaru" judul="PIN baru" @tutup="pinBaru = ''">
            <div class="rounded-lg border-2 border-amber-400 bg-amber-50 p-4 text-center">
                <div class="text-sm">Catat PIN baru ini (hanya tampil sekali)</div>
                <div class="text-4xl font-bold tracking-[0.3em]" data-testid="pin-baru">{{ pinBaru }}</div>
            </div>
            <button class="btn btn-primary" @click="pinBaru = ''">Sudah dicatat</button>
        </Modal>

        <Modal v-if="koreksi" judul="Koreksi saldo" @tutup="koreksi = null">
            <form class="flex flex-col gap-3" @submit.prevent="simpanKoreksi">
                <div>
                    <label class="label" for="k-arah">Jenis koreksi</label>
                    <select id="k-arah" v-model="koreksi.arah" class="input"><option value="kredit">Tambah saldo</option><option value="debit">Kurangi saldo</option></select>
                </div>
                <div><label class="label" for="k-nominal">Nominal (Rp)</label><input id="k-nominal" v-model="koreksi.nominal" class="input" inputmode="numeric" required></div>
                <div><label class="label" for="k-alasan">Alasan (wajib)</label><input id="k-alasan" v-model="koreksi.alasan" class="input" minlength="5" required></div>
                <p v-if="galatKoreksi" class="pesan-error" role="alert">{{ galatKoreksi }}</p>
                <button class="btn btn-primary" :disabled="proses || !koreksi.nominal || koreksi.alasan.trim().length < 5">Simpan koreksi</button>
            </form>
        </Modal>
    </AdminLayout>
</template>
