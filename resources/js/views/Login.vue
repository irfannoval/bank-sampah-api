<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login, rumah } from '../lib/auth';

const router = useRouter();
const noHp = ref('');
const pin = ref('');
const galat = ref('');
const proses = ref(false);

async function masuk() {
    galat.value = '';
    proses.value = true;
    try {
        const user = await login(noHp.value.trim(), pin.value);
        router.replace(rumah(user.role));
    } catch (e) {
        galat.value = e.pesan || e.message;
        pin.value = '';
    } finally {
        proses.value = false;
    }
}
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
        <div class="text-center">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-brand-600 text-3xl text-white">&#9851;</div>
            <h1 class="text-2xl font-bold">Bank Sampah Digital</h1>
            <p class="text-muted">Masuk untuk melanjutkan</p>
        </div>
        <form class="card flex flex-col gap-4" @submit.prevent="masuk">
            <div>
                <label class="label" for="hp">Nomor HP</label>
                <input id="hp" v-model="noHp" class="input" type="tel" inputmode="numeric" autocomplete="username" placeholder="08xxxxxxxxxx" required>
            </div>
            <div>
                <label class="label" for="pin">PIN (6 digit)</label>
                <input id="pin" v-model="pin" class="input" type="password" inputmode="numeric" maxlength="6" autocomplete="current-password" placeholder="••••••" required>
            </div>
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <button class="btn btn-primary" :disabled="proses || pin.length !== 6 || !noHp">{{ proses ? 'Memeriksa...' : 'Masuk' }}</button>
        </form>
    </div>
</template>
