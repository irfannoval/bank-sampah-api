<script setup>
import { onMounted, ref } from 'vue';
import AdminLayout from '../../components/AdminLayout.vue';
import { api } from '../../lib/api';
import { usePaged } from '../../lib/paged';
import { rp, tanggal } from '../../lib/format';

const selisih = ref(null);
const galat = ref('');
const audit = usePaged('/admin/audit-log', 30);

async function periksa() {
    galat.value = '';
    try {
        selisih.value = await api.get('/admin/selisih-saldo');
    } catch (e) {
        galat.value = e.message;
    }
}
onMounted(() => {
    periksa();
    audit.cari();
});

const ringkas = (v) => {
    if (!v) return '';
    const t = typeof v === 'string' ? v : JSON.stringify(v);
    return t.length > 90 ? t.slice(0, 90) + '…' : t;
};
</script>

<template>
    <AdminLayout judul="Audit & Cek Saldo">
        <section class="card mb-6">
            <h2 class="text-lg font-bold">Pengecekan saldo vs ledger</h2>
            <p class="text-sm text-muted">Saldo setiap nasabah harus sama dengan jumlah seluruh mutasinya. Jalankan pengecekan ini secara berkala.</p>
            <p v-if="galat" class="pesan-error" role="alert">{{ galat }}</p>
            <div v-if="selisih && !selisih.length" class="rounded-lg border border-brand-600 bg-brand-50 px-4 py-3 font-semibold" data-testid="selisih-ok">Cocok: tidak ada selisih saldo.</div>
            <div v-else-if="selisih" class="rounded-lg border border-red-300 bg-red-50 p-4" data-testid="selisih-ada">
                <strong class="text-red-800">Ada {{ selisih.length }} nasabah dengan saldo tidak cocok. Selidiki segera.</strong>
                <ul class="mt-2 text-sm"><li v-for="s in selisih" :key="s.nasabah_id">{{ s.kode_nasabah }}: tercatat {{ rp(s.saldo_tercatat) }}, dari ledger {{ rp(s.saldo_dari_ledger) }}</li></ul>
            </div>
            <div><button class="btn btn-ghost" @click="periksa">Periksa lagi</button></div>
        </section>

        <h2 class="mb-2 text-lg font-bold">Audit log</h2>
        <p v-if="audit.galat.value" class="pesan-error mb-3" role="alert">{{ audit.galat.value }}</p>
        <div class="flex flex-col gap-2">
            <div v-for="a in audit.items.value" :key="a.id" class="card !p-3 text-sm">
                <div class="flex justify-between"><strong>{{ a.aksi }}</strong><span class="text-muted">{{ tanggal(a.created_at) }}</span></div>
                <div class="text-muted">Pengguna #{{ a.user_id ?? '-' }} · {{ a.tabel || '-' }}</div>
                <div v-if="a.data_baru" class="break-all text-xs text-muted">{{ ringkas(a.data_baru) }}</div>
            </div>
        </div>
        <button v-if="!audit.terakhir.value" class="btn btn-ghost mt-3 w-full" @click="audit.muatLagi()">Muat lebih banyak</button>
    </AdminLayout>
</template>
