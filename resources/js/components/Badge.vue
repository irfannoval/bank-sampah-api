<script setup>
defineProps({ teks: String, warna: { type: String, default: 'abu' } });
const peta = {
    hijau: 'bg-brand-50 text-brand-700',
    merah: 'bg-red-50 text-red-700',
    kuning: 'bg-amber-50 text-amber-700',
    biru: 'bg-blue-50 text-blue-700',
    abu: 'bg-gray-100 text-gray-700',
};
</script>

<template>
    <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="peta[warna]">{{ teks }}</span>
</template>
