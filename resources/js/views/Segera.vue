<script setup>
import { useRouter } from 'vue-router';
import Halaman from '../components/Halaman.vue';
import { auth, logout } from '../lib/auth';

const router = useRouter();
async function keluar() {
    await logout();
    router.replace('/login');
}
</script>

<template>
    <Halaman judul="Bank Sampah Digital">
        <div class="card text-center">
            <p class="text-lg font-semibold">Halo, {{ auth.user?.nama }}</p>
            <p class="mt-2 text-muted">Tampilan untuk peran ini sedang dibangun. Untuk sementara gunakan akun toko mitra untuk mencoba layar kasir.</p>
        </div>
        <button class="btn btn-ghost" @click="keluar">Keluar</button>
    </Halaman>
</template>
