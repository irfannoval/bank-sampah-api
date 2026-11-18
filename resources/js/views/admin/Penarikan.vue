<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import Badge from '../../components/Badge.vue';
import PilihNasabah from '../../components/PilihNasabah.vue';
import { api, buatKunci } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const daftar = usePaged('/admin/penarikan');
const filter = ref('');
const muatDaftar = () => daftar.cari({ status: filter.value });
onMounted(muatDaftar);

const nasabah = ref(null);
const kartuId = ref(null);
const form = ref({ nominal: '', tipe: 'e-wallet', nomor: '' });
const rincian = ref(null);   // { biaya_admin, nominal_diterima, nama }
const kunci = ref(null);
const pin = ref('');
const galat = ref('');
const proses = ref(false);
const sukses = ref(null);

const nominalAngka = computed(() => form.value.nominal.replace(/\D/g, ''));
const siapPeriksa = computed(() => nasabah.value && nominalAngka.value && form.value.nomor.trim());

function pilih({ nasabah: n, kartuId: k }) {
    nasabah.value = n;
    kartuId.value = k;
    sukses.value = null;
}
function reset() {
    nasabah.value = null;
    kartuId.value = null;
    form.value = { nominal: '', tipe: 'e-wallet', nomor: '' };
    rincian.value = null;
    pin.value = '';
    galat.value = '';
}

async function periksa() {
    galat.value = '';
    proses.value = true;
    try {
        const [b, r] = await Promise.all([
            api.get(`/admin/penarikan/biaya?nominal=${nominalAngka.value}`),
            api.post('/admin/penarikan/validasi-rekening', { tujuan_tipe: form.value.tipe, tujuan_nomor: form.value.nomor.trim() }),
        ]);
        rincian.value = { ...b, nama: r.nama };
        kunci.value = buatKunci();
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}

async function proses_() {
    galat.value = '';
    proses.value = true;
    try {
        const r = await api.post(
            '/admin/penarikan',
            { nasabah_id: nasabah.value.id, nominal: nominalAngka.value, tujuan_tipe: form.value.tipe, tujuan_nomor: form.value.nomor.trim(), pin: pin.value },
            { idem: kunci.value }
        );
        sukses.value = { ...r, nama: nasabah.value.nama };
        reset();
        muatDaftar();
    } catch (e) {
        galat.value = e.message;
        pin.value = '';
    } finally {
        proses.value = false;
    }
}

const warna = { pending: 'kuning', berhasil: 'hijau', gagal: 'merah' };
</script>

<template>
    <AdminLayout judul="Penarikan Nasabah">
        <p v-if="sukses" class="card mb-4 !border-brand-600 !bg-brand-50" role="status" data-testid="sukses">
            Penarikan {{ rp(sukses.nominal) }} untuk {{ sukses.nama }} diproses. Nasabah menerima {{ rp(sukses.nominal_diterima) }} (status: {{ sukses.status }}).
        </p>

        <section class="card mb-6">
            <h2 class="text-lg font-bold">Penarikan untuk nasabah tanpa HP</h2>
            <PilihNasabah :nasabah="nasabah" :tanpa-kartu="!!nasabah && !kartuId" @pilih="pilih" @ganti="reset" />

            <template v-if="nasabah">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label class="label" for="p-nominal">Jumlah penarikan (Rp)</label><input id="p-nominal" v-model="form.nominal" class="input" inputmode="numeric" :disabled="!!rincian"></div>
                    <div>
                        <label class="label" for="p-tipe">Tujuan</label>
                        <select id="p-tipe" v-model="form.tipe" class="input" :disabled="!!rincian"><option value="e-wallet">E-wallet</option><option value="bank">Rekening bank</option></select>
                    </div>
                    <div><label class="label" for="p-nomor">Nomor tujuan</label><input id="p-nomor" v-model="form.nomor" class="input" inputmode="numeric" :disabled="!!rincian"></div>
                </div>

                <button v-if="!rincian" class="btn btn-primary sm:self-start" :disabled="proses || !siapPeriksa" @click="periksa">Periksa rekening & biaya</button>

                <div v-else class="flex flex-col gap-3">
                    <div class="rounded-lg bg-gray-50 p-4" data-testid="rincian">
                        <div class="flex justify-between"><span>Pemilik rekening</span><strong>{{ rincian.nama }}</strong></div>
                        <div class="flex justify-between"><span>Dipotong dari saldo</span><strong>{{ rp(rincian.nominal) }}</strong></div>
                        <div class="flex justify-between"><span>Biaya admin (ditanggung nasabah)</span><strong>{{ rp(rincian.biaya_admin) }}</strong></div>
                        <div class="flex justify-between border-t border-line pt-2 text-lg"><span>Nasabah menerima</span><strong>{{ rp(rincian.nominal_diterima) }}</strong></div>
                    </div>
                    <div class="max-w-xs">
                        <label class="label" for="p-pin">PIN nasabah (diketik nasabah sendiri)</label>
                        <input id="p-pin" v-model="pin" class="input text-center tracking-[0.5em]" type="password" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="••••••">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button class="btn btn-ghost" :disabled="proses" @click="rincian = null; pin = ''">Ubah data</button>
                        <button class="btn btn-primary" :disabled="proses || pin.length !== 6" @click="proses_">{{ proses ? 'Memproses...' : 'Proses penarikan' }}</button>
                    </div>
                </div>
                <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            </template>
            <p v-else-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
        </section>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">Semua penarikan</h2>
            <select v-model="filter" class="input !w-auto" aria-label="Filter status" @change="muatDaftar">
                <option value="">Semua status</option><option value="pending">Menunggu</option><option value="berhasil">Berhasil</option><option value="gagal">Gagal</option>
            </select>
        </div>
        <div class="flex flex-col gap-2">
            <div v-for="p in daftar.items.value" :key="p.id" class="card !flex-row items-center justify-between !p-4">
                <div>
                    <strong>{{ p.nama }}</strong> <span class="text-sm text-muted">{{ p.kode_nasabah }}</span>
                    <div class="text-sm text-muted">{{ tanggal(p.created_at) }} · {{ p.tujuan_tipe }} {{ p.tujuan_nomor }}</div>
                </div>
                <div class="text-right"><div class="font-bold">{{ rp(p.nominal) }}</div><Badge :teks="p.status" :warna="warna[p.status]" /></div>
            </div>
        </div>
        <p v-if="!daftar.items.value.length && !daftar.muat.value" class="py-4 text-center text-muted">Belum ada penarikan.</p>
        <button v-if="!daftar.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="daftar.muatLagi()">Muat lebih banyak</button>
    </AdminLayout>
</template>
