<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import QrScanner from '../../components/QrScanner.vue';
import { api, buatKunci } from '../../lib/api';
import { rp } from '../../lib/format';

// ---- data master ----
const jenis = ref([]);
const galatMuat = ref('');

// ---- pilih nasabah ----
const kolom = ref('');
const kolomEl = ref(null);
const kamera = ref(false);
const nasabah = ref(null);   // { id, kode_nasabah, nama, saldo }
const kartuId = ref(null);
const hasilCari = ref([]);
const galatNasabah = ref('');
const mencari = ref(false);

// ---- isi sampah ----
const baris = ref([{ jenis_id: '', berat: '' }]);

// ---- simpan ----
const ringkasan = ref(null); // hasil hitung dari server
const kunci = ref(null);
const proses = ref(false);
const galatSimpan = ref('');
const sukses = ref(null);

const TOKEN = /^[a-f0-9]{32,64}$/i;
const REGEX_BERAT = /^\d{1,9}(\.\d{1,3})?$/;

onMounted(async () => {
    try {
        jenis.value = (await api.get('/admin/jenis-sampah')).filter((j) => j.aktif);
    } catch (e) {
        galatMuat.value = e.message;
    }
    kolomEl.value?.focus();
});

const jenisById = computed(() => Object.fromEntries(jenis.value.map((j) => [String(j.id), j])));
const normal = (b) => String(b).trim().replace(',', '.');
const beratValid = (b) => REGEX_BERAT.test(normal(b)) && Number(normal(b)) > 0;
const baikBaris = (r) => r.jenis_id && beratValid(r.berat);
const subtotal = (r) => (baikBaris(r) ? Number(normal(r.berat)) * Number(jenisById.value[r.jenis_id].harga_per_kg) : 0);
const totalBerat = computed(() => baris.value.filter(baikBaris).reduce((a, r) => a + Number(normal(r.berat)), 0));
const totalNilai = computed(() => baris.value.reduce((a, r) => a + subtotal(r), 0));
const bisaSimpan = computed(() => nasabah.value && baris.value.some(baikBaris) && baris.value.every((r) => !r.jenis_id && !r.berat || baikBaris(r)));

async function cari() {
    const teks = kolom.value.trim();
    if (!teks) return;
    galatNasabah.value = '';
    hasilCari.value = [];
    mencari.value = true;
    try {
        if (TOKEN.test(teks)) {
            const r = await api.post('/admin/kartu/scan', { qr_token: teks });
            pilih(r.nasabah, r.kartu_id);
        } else {
            const r = await api.get('/admin/nasabah?q=' + encodeURIComponent(teks));
            hasilCari.value = r.data.filter((n) => n.status === 'aktif');
            if (!hasilCari.value.length) galatNasabah.value = 'Nasabah tidak ditemukan.';
        }
    } catch (e) {
        galatNasabah.value = e.message;
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

function pilih(n, idKartu = null) {
    nasabah.value = { id: n.id, kode_nasabah: n.kode_nasabah, nama: n.nama, saldo: n.saldo };
    kartuId.value = idKartu;
    hasilCari.value = [];
    galatNasabah.value = '';
    sukses.value = null;
}

function gantiNasabah() {
    nasabah.value = null;
    kartuId.value = null;
    nextTick(() => kolomEl.value?.focus());
}

function tambahBaris() {
    baris.value.push({ jenis_id: '', berat: '' });
}
function hapusBaris(i) {
    baris.value.splice(i, 1);
    if (!baris.value.length) tambahBaris();
}

function itemKirim() {
    return baris.value.filter(baikBaris).map((r) => ({ jenis_sampah_id: Number(r.jenis_id), berat: normal(r.berat) }));
}

async function periksa() {
    galatSimpan.value = '';
    proses.value = true;
    try {
        ringkasan.value = await api.post('/admin/setoran/hitung', { items: itemKirim() });
        kunci.value = buatKunci();
    } catch (e) {
        galatSimpan.value = e.message;
    } finally {
        proses.value = false;
    }
}

async function simpan() {
    galatSimpan.value = '';
    proses.value = true;
    try {
        const r = await api.post(
            '/admin/setoran',
            { nasabah_id: nasabah.value.id, kartu_id: kartuId.value, items: itemKirim() },
            { idem: kunci.value }
        );
        sukses.value = { ...r, nama: nasabah.value.nama };
        ringkasan.value = null;
        nasabah.value = null;
        kartuId.value = null;
        baris.value = [{ jenis_id: '', berat: '' }];
        nextTick(() => kolomEl.value?.focus());
    } catch (e) {
        galatSimpan.value = e.message;
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <AdminLayout judul="Input Setoran">
        <p v-if="galatMuat" class="pesan-error mb-4" role="alert">{{ galatMuat }}</p>

        <div v-if="sukses" class="card mb-4 !border-brand-600 !bg-brand-50" role="status" data-testid="sukses">
            <strong class="text-lg">Setoran tersimpan</strong>
            <span>{{ sukses.nama }}: {{ sukses.total_berat }} kg, {{ rp(sukses.total_nilai) }}</span>
            <span class="text-sm text-muted">Saldo nasabah sekarang {{ rp(sukses.saldo_sesudah) }}</span>
        </div>

        <!-- 1. Nasabah -->
        <section class="card mb-4">
            <h2 class="text-lg font-bold">1. Pilih nasabah</h2>

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
                <p v-if="galatNasabah" class="pesan-error mt-3" role="alert">{{ galatNasabah }}</p>

                <ul v-if="hasilCari.length" class="mt-3 divide-y divide-line rounded-lg border border-line">
                    <li v-for="n in hasilCari" :key="n.id">
                        <button class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-gray-50" @click="pilih(n)">
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
                    <div class="text-sm text-muted">Saldo {{ rp(nasabah.saldo) }}<span v-if="!kartuId"> · dicari manual (tanpa kartu)</span></div>
                </div>
                <button class="btn btn-ghost !min-h-10" @click="gantiNasabah">Ganti</button>
            </div>
        </section>

        <!-- 2. Sampah -->
        <section class="card mb-4">
            <h2 class="text-lg font-bold">2. Isi sampah</h2>
            <div class="hidden grid-cols-[2fr_1fr_1fr_1fr_44px] gap-3 text-sm font-semibold text-muted sm:grid">
                <span>Jenis sampah</span><span>Berat (kg)</span><span>Harga/kg</span><span>Subtotal</span><span></span>
            </div>
            <div
                v-for="(r, i) in baris"
                :key="i"
                class="grid grid-cols-2 items-center gap-3 border-t border-line pt-3 sm:grid-cols-[2fr_1fr_1fr_1fr_44px] sm:border-0 sm:pt-0"
            >
                <select v-model="r.jenis_id" class="input col-span-2 sm:col-span-1" :aria-label="`Jenis sampah baris ${i + 1}`">
                    <option value="">Pilih jenis sampah</option>
                    <option v-for="j in jenis" :key="j.id" :value="String(j.id)">{{ j.nama }}</option>
                </select>
                <input v-model="r.berat" class="input" inputmode="decimal" placeholder="0,0" :aria-label="`Berat baris ${i + 1}`">
                <span class="text-muted">{{ r.jenis_id ? rp(jenisById[r.jenis_id].harga_per_kg) + '/kg' : '-' }}</span>
                <strong>{{ rp(subtotal(r)) }}</strong>
                <button class="h-11 w-11 text-xl text-muted hover:text-red-700" :aria-label="`Hapus baris ${i + 1}`" @click="hapusBaris(i)">&times;</button>
            </div>
            <div><button class="btn btn-ghost" @click="tambahBaris">+ Tambah baris</button></div>
        </section>

        <!-- 3. Simpan -->
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-lg" data-testid="total">
                Total <strong>{{ totalBerat.toLocaleString('id-ID', { maximumFractionDigits: 3 }) }} kg</strong>
                · Saldo diterima nasabah <strong class="text-2xl">{{ rp(totalNilai) }}</strong>
            </div>
            <button class="btn btn-primary sm:min-w-52" :disabled="!bisaSimpan || proses" @click="periksa">Periksa &amp; simpan</button>
        </section>
        <p v-if="galatSimpan && !ringkasan" class="pesan-error mt-3" role="alert">{{ galatSimpan }}</p>

        <!-- Konfirmasi -->
        <div v-if="ringkasan" class="fixed inset-0 z-20 flex items-end justify-center bg-black/40 p-4 sm:items-center" role="dialog" aria-label="Konfirmasi setoran">
            <div class="card w-full max-w-lg">
                <h2 class="text-lg font-bold">Konfirmasi setoran</h2>
                <p>Nasabah: <strong>{{ nasabah.nama }}</strong> ({{ nasabah.kode_nasabah }})</p>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="b in ringkasan.baris" :key="b.jenis_sampah_id + b.berat" class="border-t border-line">
                            <td class="py-2">{{ b.nama }}</td>
                            <td class="py-2 text-right">{{ Number(b.berat) }} kg</td>
                            <td class="py-2 text-right">{{ rp(b.subtotal) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex justify-between border-t border-line pt-3 text-lg">
                    <span>{{ Number(ringkasan.total_berat) }} kg</span>
                    <strong data-testid="total-server">{{ rp(ringkasan.total_nilai) }}</strong>
                </div>
                <p v-if="galatSimpan" class="pesan-error" role="alert">{{ galatSimpan }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <button class="btn btn-ghost" :disabled="proses" @click="ringkasan = null; galatSimpan = ''">Kembali</button>
                    <button class="btn btn-primary" :disabled="proses" @click="simpan">{{ proses ? 'Menyimpan...' : 'Simpan setoran' }}</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
