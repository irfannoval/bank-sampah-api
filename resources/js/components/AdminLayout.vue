<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { auth, logout } from '../lib/auth';

defineProps({ judul: String });
const router = useRouter();

const semua = [
    { ke: '/admin/setoran', teks: 'Input Setoran' },
    { ke: '/admin/riwayat-setoran', teks: 'Riwayat Setoran' },
    { ke: '/admin/nasabah', teks: 'Nasabah' },
    { ke: '/admin/jenis-sampah', teks: 'Jenis Sampah' },
    { ke: '/admin/mitra', teks: 'Toko Mitra' },
    { ke: '/admin/transaksi', teks: 'Transaksi Mitra' },
    { ke: '/admin/pencairan', teks: 'Pencairan' },
    { ke: '/admin/penarikan', teks: 'Penarikan' },
    { ke: '/admin/laporan', teks: 'Laporan' },
    { ke: '/admin/pengaturan', teks: 'Pengaturan', utama: true },
    { ke: '/admin/sistem', teks: 'Audit & Cek Saldo', utama: true },
    { ke: '/ubah-pin', teks: 'Ubah PIN' },
];
const menu = computed(() => semua.filter((m) => !m.utama || auth.user?.role === 'admin_utama'));

async function keluar() {
    await logout();
    router.replace('/login');
}
</script>

<template>
    <div class="min-h-dvh bg-ground md:flex">
        <aside class="border-b border-line bg-white md:min-h-dvh md:w-56 md:shrink-0 md:border-b-0 md:border-r">
            <div class="flex items-center justify-between px-4 py-3 md:block">
                <strong class="text-lg">Bank Sampah</strong>
                <span class="text-sm text-muted md:mt-1 md:block">{{ auth.user?.nama }}</span>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-2 pb-2 md:flex-col md:pb-0">
                <router-link
                    v-for="m in menu"
                    :key="m.ke"
                    :to="m.ke"
                    class="whitespace-nowrap rounded-lg px-3 py-3 text-[15px] hover:bg-gray-50"
                    active-class="!bg-brand-50 font-bold"
                >{{ m.teks }}</router-link>
                <button class="whitespace-nowrap rounded-lg px-3 py-3 text-left text-[15px] text-muted hover:bg-gray-50 md:mt-4" @click="keluar">Keluar</button>
            </nav>
        </aside>
        <main class="mx-auto w-full max-w-5xl flex-1 p-4 md:p-8">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">{{ judul }}</h1>
                <div><slot name="aksi" /></div>
            </div>
            <slot />
        </main>
    </div>
</template>
