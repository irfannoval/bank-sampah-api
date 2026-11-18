<script setup>
defineProps({ judul: String, lebar: { type: String, default: 'max-w-lg' } });
const emit = defineEmits(['tutup']);
</script>

<template>
    <div class="fixed inset-0 z-30 flex items-end justify-center overflow-y-auto bg-black/40 p-4 sm:items-center" role="dialog" aria-modal="true" :aria-label="judul">
        <div class="card w-full !gap-3" :class="lebar">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold">{{ judul }}</h2>
                <button type="button" class="h-10 w-10 text-2xl text-muted" aria-label="Tutup" @click="emit('tutup')">&times;</button>
            </div>
            <slot />
        </div>
    </div>
</template>
