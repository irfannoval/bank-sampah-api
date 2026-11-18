// Klien API tipis. Semua uang dikirim/diterima sebagai string desimal ("50000.00").
const BASE = '/api/v1';

export class ApiError extends Error {
    constructor(status, kode, pesan, data = null) {
        super(pesan);
        this.status = status;
        this.kode = kode;
        this.data = data;
    }
}

let onUnauthorized = () => {};
export function setUnauthorizedHandler(fn) {
    onUnauthorized = fn;
}

export function buatKunci() {
    return crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2);
}

async function request(method, path, body, opts = {}) {
    const headers = { Accept: 'application/json' };
    const token = localStorage.getItem('bs_token');
    if (token) headers.Authorization = 'Bearer ' + token;
    if (body !== undefined && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    if (opts.idem) headers['Idempotency-Key'] = opts.idem;

    let res;
    try {
        res = await fetch(BASE + path, {
            method,
            headers,
            body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, 'JARINGAN', 'Tidak bisa terhubung ke server. Periksa sinyal internet.');
    }

    let data = null;
    try {
        data = await res.json();
    } catch { /* balasan bukan JSON */ }

    if (!res.ok) {
        if (res.status === 401 && token) onUnauthorized();
        const e = data && data.error;
        let pesan = (e && e.pesan) || (data && data.message) || 'Terjadi kesalahan. Coba lagi.';
        if (data && data.errors) pesan = Object.values(data.errors).flat()[0] || pesan;
        throw new ApiError(res.status, (e && e.kode) || (res.status === 422 ? 'VALIDASI' : 'ERROR'), pesan, data);
    }
    return data;
}

export const api = {
    get: (path) => request('GET', path),
    post: (path, body = {}, opts) => request('POST', path, body, opts),
    patch: (path, body = {}, opts) => request('PATCH', path, body, opts),
};

/** Unduh berkas yang butuh login (mis. bukti transfer). Mengembalikan Blob. */
export async function unduh(path) {
    const token = localStorage.getItem('bs_token');
    let res;
    try {
        res = await fetch(BASE + path, { headers: { Authorization: 'Bearer ' + token } });
    } catch {
        throw new ApiError(0, 'JARINGAN', 'Tidak bisa terhubung ke server.');
    }
    if (!res.ok) throw new ApiError(res.status, 'ERROR', 'Berkas tidak ditemukan.');
    return res.blob();
}
