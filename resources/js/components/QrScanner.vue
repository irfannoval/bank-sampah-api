<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';

const props = defineProps({ aktif: { type: Boolean, default: true } });
const emit = defineEmits(['terbaca']);

const galat = ref('');
const berjalan = ref(false);
let pemindai = null;
let terakhir = '';
let waktuTerakhir = 0;

const aman = window.isSecureContext || ['localhost', '127.0.0.1'].includes(location.hostname);

async function mulai() {
    if (!aman) {
        galat.value = 'Kamera hanya bisa dipakai lewat alamat HTTPS. Gunakan input kode di bawah.';
        return;
    }
    try {
        pemindai = new Html5Qrcode('qr-area');
        await pemindai.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            (teks) => {
                const sekarang = Date.now();
                // Cegah pembacaan ganda untuk QR yang sama dalam 3 detik.
                if (teks === terakhir && sekarang - waktuTerakhir < 3000) return;
                terakhir = teks;
                waktuTerakhir = sekarang;
                if (props.aktif) emit('terbaca', teks.trim());
            },
            () => {}
        );
        berjalan.value = true;
    } catch {
        galat.value = 'Kamera tidak bisa dibuka. Izinkan akses kamera di browser, atau gunakan input kode di bawah.';
    }
}

async function berhenti() {
    if (pemindai && berjalan.value) {
        try {
            await pemindai.stop();
            pemindai.clear();
        } catch { /* sudah berhenti */ }
    }
}

onMounted(mulai);
onBeforeUnmount(berhenti);
</script>

<template>
    <div>
        <div id="qr-area" class="mx-auto aspect-square w-full max-w-[300px] overflow-hidden rounded-2xl border-2 border-dashed border-gray-500 bg-black/5"></div>
        <p v-if="galat" class="pesan-error mt-3">{{ galat }}</p>
        <p v-else class="mt-3 text-center text-sm text-muted">Arahkan kamera ke QR di kartu nasabah</p>
    </div>
</template>
