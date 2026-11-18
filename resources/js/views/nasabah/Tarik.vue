<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Halaman from '../../components/Halaman.vue';
import Numpad from '../../components/Numpad.vue';
import { api, buatKunci } from '../../lib/api';
import { rp } from '../../lib/format';

const router = useRouter();
const langkah = ref('isi'); // isi -> periksa -> pin -> hasil
const form = ref({ nominal: '', bank: '', nomor: '' });
const banks = ref([]);
const rincian = ref(null);
const kunci = ref(null);
const pin = ref('');
const galat = ref('');
const proses = ref(false);
const hasil = ref(null);

const nominal = computed(() => form.value.nominal.replace(/\D/g, ''));

onMounted(async () => {
    try {
        banks.value = await api.get('/gateway/banks');
    } catch (e) {
        galat.value = e.message;
    }
});

async function periksa() {
    galat.value = '';
    proses.value = true;
    try {
        const [b, r] = await Promise.all([
            api.get(`/nasabah/penarikan/biaya?nominal=${nominal.value}`),
            api.post('/nasabah/penarikan/validasi-rekening', { tujuan_bank: form.value.bank, tujuan_nomor: form.value.nomor.trim() }),
        ]);
        rincian.value = { ...b, nama: r.nama };
        kunci.value = buatKunci();
        langkah.value = 'periksa';
    } catch (e) {
        galat.value = e.message;
    } finally {
        proses.value = false;
    }
}

function tekan(k) {
    if (proses.value || pin.value.length >= 6) return;
    pin.value += k;
    if (pin.value.length === 6) kirim();
}

async function kirim() {
    proses.value = true;
    galat.value = '';
    try {
        hasil.value = await api.post(
            '/nasabah/penarikan',
            { nominal: nominal.value, tujuan_bank: form.value.bank, tujuan_nomor: form.value.nomor.trim(), pin: pin.value },
            { idem: kunci.value }
        );
        langkah.value = 'hasil';
    } catch (e) {
        galat.value = e.message;
        const sisa = e.data && e.data.error && e.data.error.sisa_percobaan;
        if (e.kode === 'PIN_SALAH' && sisa !== undefined) galat.value += ` Sisa percobaan: ${sisa}.`;
        pin.value = '';
        if (e.kode !== 'PIN_SALAH' && e.kode !== 'JARINGAN') langkah.value = 'isi'; // aturan penarikan: ubah data
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <Halaman judul="Tarik Saldo" :kembali="langkah === 'hasil' ? '/nasabah' : '/nasabah'">
        <template v-if="langkah === 'isi'">
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <div><label class="label" for="t-nominal">Jumlah penarikan (Rp)</label><input id="t-nominal" v-model="form.nominal" class="input" inputmode="numeric" placeholder="Contoh: 20000"></div>
            <div>
                <label class="label" for="t-bank">Bank tujuan</label>
                <select id="t-bank" v-model="form.bank" class="input" :disabled="!banks.length">
                    <option value="" disabled>Pilih bank</option>
                    <option v-for="bank in banks" :key="bank.code" :value="bank.code">{{ bank.name }}</option>
                </select>
            </div>
            <div><label class="label" for="t-nomor">Nomor tujuan</label><input id="t-nomor" v-model="form.nomor" class="input" inputmode="numeric"></div>
            <button class="btn btn-primary" :disabled="proses || !nominal || !form.bank || !form.nomor.trim()" @click="periksa">Lanjut</button>
        </template>

        <template v-else-if="langkah === 'periksa'">
            <div class="card" data-testid="rincian">
                <div class="flex justify-between"><span>Pemilik</span><strong>{{ rincian.nama }}</strong></div>
                <div class="flex justify-between"><span>Dipotong dari saldo</span><strong>{{ rp(rincian.nominal) }}</strong></div>
                <div class="flex justify-between"><span>Biaya admin</span><strong>{{ rp(rincian.biaya_admin) }}</strong></div>
                <div class="flex justify-between border-t border-line pt-2 text-lg"><span>Kamu terima</span><strong>{{ rp(rincian.nominal_diterima) }}</strong></div>
            </div>
            <p class="text-sm text-muted">Pastikan nama pemilik sudah benar sebelum lanjut.</p>
            <button class="btn btn-primary" @click="langkah = 'pin'">Benar, masukkan PIN</button>
            <button class="btn btn-ghost" @click="langkah = 'isi'">Ubah data</button>
        </template>

        <template v-else-if="langkah === 'pin'">
            <div class="text-center">Masukkan PIN 6 digit untuk menarik <strong>{{ rp(rincian.nominal) }}</strong></div>
            <div class="flex justify-center gap-3.5 py-1">
                <span v-for="i in 6" :key="i" class="h-4 w-4 rounded-full border-2 border-gray-500" :class="{ 'bg-brand-600 border-brand-600': i <= pin.length }"></span>
            </div>
            <p v-if="galat" class="pesan-error text-center" role="alert">{{ galat }}</p>
            <p v-if="proses" class="text-center text-muted">Memproses...</p>
            <Numpad @tekan="tekan" @hapus="pin = pin.slice(0, -1)" />
        </template>

        <template v-else>
            <div class="card text-center" data-testid="hasil">
                <strong class="text-2xl text-brand-600">Penarikan diproses</strong>
                <strong class="text-4xl">{{ rp(hasil.nominal_diterima) }}</strong>
                <span v-if="hasil.ref_gateway" class="text-sm text-muted">Payout {{ hasil.ref_gateway }} untuk rekening {{ hasil.tujuan_bank }} {{ hasil.tujuan_nomor }} menunggu persetujuan/transfer Midtrans.</span>
                <span v-else class="text-sm text-amber-700">Payout belum mendapat referensi dari Midtrans. Sistem akan mencoba ulang; jangan ajukan penarikan yang sama lagi.</span>
            </div>
            <button class="btn btn-primary" @click="router.replace('/nasabah')">Selesai</button>
        </template>
    </Halaman>
</template>
