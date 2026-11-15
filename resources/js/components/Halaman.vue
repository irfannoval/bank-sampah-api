<script setup>
defineProps({ judul: String, kembali: String });
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-md flex-col bg-ground">
        <header class="sticky top-0 z-10 flex h-14 items-center gap-1 border-b border-line bg-white px-2 pt-[env(safe-area-inset-top)]">
            <router-link
                v-if="kembali"
                :to="kembali"
                aria-label="Kembali"
                class="flex h-11 w-11 items-center justify-center text-2xl"
            >&larr;</router-link>
            <h1 class="px-2 text-lg font-bold">{{ judul }}</h1>
            <div class="ml-auto pr-2"><slot name="kanan" /></div>
        </header>
        <main class="flex flex-1 flex-col gap-4 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <slot />
        </main>
    </div>
</template>
