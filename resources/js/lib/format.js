const rupiah = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

/** "12500.00" -> "Rp 12.500" */
export function rp(nilai) {
    const n = Number(nilai ?? 0);
    return 'Rp ' + rupiah.format(Number.isFinite(n) ? n : 0);
}

export function tanggal(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export function jam(iso) {
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
