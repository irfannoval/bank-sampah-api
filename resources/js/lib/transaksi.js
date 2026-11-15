import { reactive } from 'vue';

/** Status satu transaksi belanja yang sedang berjalan di kasir. */
export const trx = reactive({
    sesi: null,      // { sesi_id, berlaku_sampai }
    nasabah: null,   // { nama, kode_nasabah, saldo }
    nominal: '',     // string angka, mis. "10000"
    kunci: null,     // Idempotency-Key untuk langkah konfirmasi
    hasil: null,     // balasan konfirmasi
});

export function resetTrx() {
    trx.sesi = null;
    trx.nasabah = null;
    trx.nominal = '';
    trx.kunci = null;
    trx.hasil = null;
}
