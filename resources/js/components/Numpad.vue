<script setup>
// Tombol angka besar. tombolExtra: teks tombol kiri-bawah (mis. "000"), kosongkan untuk kosong.
defineProps({ tombolExtra: { type: String, default: '' } });
const emit = defineEmits(['tekan', 'hapus']);
const kelas = 'min-h-16 rounded-lg border border-line bg-white text-2xl font-semibold active:bg-gray-100';
</script>

<template>
    <div class="grid grid-cols-3 gap-2.5">
        <button v-for="k in ['1','2','3','4','5','6','7','8','9']" :key="k" type="button" :class="kelas" @click="emit('tekan', k)">{{ k }}</button>
        <button v-if="tombolExtra" type="button" :class="kelas" @click="emit('tekan', tombolExtra)">{{ tombolExtra }}</button>
        <span v-else></span>
        <button type="button" :class="kelas" @click="emit('tekan', '0')">0</button>
        <button type="button" aria-label="Hapus" :class="kelas" @click="emit('hapus')">&larr;</button>
    </div>
</template>
