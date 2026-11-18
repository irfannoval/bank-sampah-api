import { ref } from 'vue';
import { api } from './api';

/** Daftar berhalaman dengan "muat lebih banyak". cari({status:'x'}) memuat ulang dari awal. */
export function usePaged(path, perPage = 20) {
    const items = ref([]);
    const halaman = ref(1);
    const terakhir = ref(true);
    const muat = ref(false);
    const galat = ref('');
    let filter = {};

    async function ambil(ulang = false) {
        if (ulang) {
            items.value = [];
            halaman.value = 1;
        }
        muat.value = true;
        galat.value = '';
        try {
            const q = new URLSearchParams({ page: halaman.value, per_page: perPage });
            Object.entries(filter).forEach(([k, v]) => v !== '' && v != null && q.set(k, v));
            const r = await api.get(`${path}${path.includes('?') ? '&' : '?'}${q}`);
            items.value.push(...r.data);
            terakhir.value = r.current_page >= r.last_page;
            halaman.value++;
        } catch (e) {
            galat.value = e.message;
        } finally {
            muat.value = false;
        }
    }

    return {
        items, terakhir, muat, galat,
        cari: (f = {}) => { filter = f; return ambil(true); },
        muatLagi: () => ambil(false),
    };
}
