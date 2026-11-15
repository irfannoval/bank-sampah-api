import { reactive } from 'vue';
import { api } from './api';

export const auth = reactive({
    token: localStorage.getItem('bs_token'),
    user: JSON.parse(localStorage.getItem('bs_user') || 'null'),
});

export function rumah(role) {
    if (role === 'mitra') return '/mitra';
    if (role === 'nasabah') return '/nasabah';
    if (role === 'admin' || role === 'admin_utama') return '/admin';
    return '/login';
}

export async function login(noHp, pin) {
    const r = await api.post('/auth/login', { no_hp: noHp, pin });
    auth.token = r.token;
    auth.user = r.user;
    localStorage.setItem('bs_token', r.token);
    localStorage.setItem('bs_user', JSON.stringify(r.user));
    return r.user;
}

export function bersihkanSesi() {
    auth.token = null;
    auth.user = null;
    localStorage.removeItem('bs_token');
    localStorage.removeItem('bs_user');
}

export async function logout() {
    try {
        await api.post('/auth/logout');
    } catch { /* tidak masalah jika gagal */ }
    bersihkanSesi();
}
