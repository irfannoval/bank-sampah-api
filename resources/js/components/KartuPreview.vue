<script setup>
import { ref, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({ nama: String, kode: String, token: String });
const gambar = ref('');

watch(
    () => props.token,
    async (t) => {
        gambar.value = t ? await QRCode.toDataURL(t, { margin: 1, width: 320, errorCorrectionLevel: 'M' }) : '';
    },
    { immediate: true }
);

// Ukuran kartu ATM standar: 85,6 x 54 mm
</script>

<template>
    <div
        class="area-cetak flex h-[54mm] w-[85.6mm] shrink-0 overflow-hidden rounded-[3mm] border border-gray-300 bg-white text-ink shadow"
        data-testid="kartu"
    >
        <div class="flex flex-1 flex-col justify-between bg-brand-600 p-[4mm] text-white">
            <div>
                <div class="text-[4.5mm] font-bold leading-tight">BANK SAMPAH</div>
                <div class="text-[2.6mm] opacity-90">Kartu Nasabah</div>
            </div>
            <div>
                <div class="text-[4mm] font-semibold leading-tight">{{ nama }}</div>
                <div class="text-[3.2mm] tracking-wider">{{ kode }}</div>
            </div>
        </div>
        <div class="flex w-[40mm] items-center justify-center bg-white p-[2mm]">
            <img v-if="gambar" :src="gambar" alt="QR kartu nasabah" class="h-full w-full object-contain">
        </div>
    </div>
</template>
