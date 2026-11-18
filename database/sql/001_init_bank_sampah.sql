-- =====================================================================
-- Bank Sampah Digital - Migration 001 (skema awal)
-- Database : PostgreSQL 14+
-- Acuan    : Dokumen Perancangan Sistem v1.2
-- Catatan  : semua nominal DECIMAL (numeric), bukan float.
--            Perubahan saldo HARUS lewat DB transaction:
--            update saldo + insert mutasi_saldo dalam satu transaksi.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. USERS (akun login semua peran)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nama             varchar(120) NOT NULL,
    no_hp            varchar(20)  NOT NULL UNIQUE,
    pin_hash         varchar(255) NOT NULL,           -- hash (bcrypt/argon2), bukan PIN asli
    pin_gagal        smallint     NOT NULL DEFAULT 0, -- hitungan salah PIN berturut-turut
    terkunci_sampai  timestamptz,                     -- terisi saat akun terkunci sementara
    role             varchar(12)  NOT NULL
                     CHECK (role IN ('admin_utama','admin','nasabah','mitra')),
    status           varchar(10)  NOT NULL DEFAULT 'aktif'
                     CHECK (status IN ('aktif','nonaktif')),
    created_at       timestamptz  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 2. NASABAH
-- ---------------------------------------------------------------------
CREATE TABLE nasabah (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id        bigint        NOT NULL UNIQUE REFERENCES users(id),
    kode_nasabah   varchar(20)   NOT NULL UNIQUE,
    alamat         text,
    saldo          numeric(14,2) NOT NULL DEFAULT 0 CHECK (saldo >= 0), -- cache; sumber kebenaran: mutasi_saldo
    created_at     timestamptz   NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 3. KARTU (kartu fisik ber-QR milik nasabah)
-- ---------------------------------------------------------------------
CREATE TABLE kartu (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nasabah_id          bigint       NOT NULL REFERENCES nasabah(id),
    qr_token            varchar(64)  NOT NULL UNIQUE, -- token ACAK, bukan data saldo/pribadi
    status              varchar(10)  NOT NULL DEFAULT 'aktif'
                        CHECK (status IN ('aktif','diblokir','hilang')),
    dicetak_pada        timestamptz  NOT NULL DEFAULT now(),
    kartu_pengganti_id  bigint REFERENCES kartu(id)
);
-- Satu nasabah hanya boleh punya satu kartu aktif.
CREATE UNIQUE INDEX ux_kartu_aktif ON kartu (nasabah_id) WHERE status = 'aktif';

-- ---------------------------------------------------------------------
-- 4. TOKO MITRA
-- ---------------------------------------------------------------------
CREATE TABLE toko_mitra (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id        bigint        NOT NULL UNIQUE REFERENCES users(id),
    nama_toko      varchar(150)  NOT NULL,
    alamat         text,
    saldo_tagihan  numeric(14,2) NOT NULL DEFAULT 0 CHECK (saldo_tagihan >= 0),
    bank_tujuan    varchar(60),
    no_rekening    varchar(40),
    atas_nama      varchar(120),
    created_at     timestamptz   NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 5. JENIS SAMPAH
-- ---------------------------------------------------------------------
CREATE TABLE jenis_sampah (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nama          varchar(100)  NOT NULL UNIQUE,
    kategori      varchar(60),
    harga_per_kg  numeric(12,2) NOT NULL CHECK (harga_per_kg >= 0),
    aktif         boolean       NOT NULL DEFAULT true
);

-- ---------------------------------------------------------------------
-- 6. SETORAN + DETAIL
-- ---------------------------------------------------------------------
CREATE TABLE setoran (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nasabah_id    bigint        NOT NULL REFERENCES nasabah(id),
    kartu_id      bigint        REFERENCES kartu(id),   -- kosong jika admin mencari manual
    admin_id      bigint        NOT NULL REFERENCES users(id),
    total_berat   numeric(12,3) NOT NULL CHECK (total_berat > 0),
    total_nilai   numeric(14,2) NOT NULL CHECK (total_nilai >= 0),
    status        varchar(12)   NOT NULL DEFAULT 'berhasil'
                  CHECK (status IN ('berhasil','dibatalkan')),
    dibatalkan_oleh bigint      REFERENCES users(id),   -- hanya admin_utama (diatur di aplikasi)
    alasan_batal  text,
    waktu_batal   timestamptz,
    created_at    timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT setoran_batal_lengkap CHECK (
        status = 'berhasil'
        OR (dibatalkan_oleh IS NOT NULL AND alasan_batal IS NOT NULL AND waktu_batal IS NOT NULL)
    )
);

CREATE TABLE detail_setoran (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    setoran_id       bigint        NOT NULL REFERENCES setoran(id),
    jenis_sampah_id  bigint        NOT NULL REFERENCES jenis_sampah(id),
    berat            numeric(12,3) NOT NULL CHECK (berat > 0),
    harga_saat_itu   numeric(12,2) NOT NULL CHECK (harga_saat_itu >= 0), -- snapshot harga
    subtotal         numeric(14,2) NOT NULL CHECK (subtotal >= 0)
);

-- ---------------------------------------------------------------------
-- 7. PENCAIRAN MITRA (settlement ke toko mitra)
--    Selama status 'pending', nominal dikunci dari saldo_tagihan (diatur di aplikasi).
-- ---------------------------------------------------------------------
CREATE TABLE pencairan_mitra (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    toko_mitra_id  bigint        NOT NULL REFERENCES toko_mitra(id),
    nominal        numeric(14,2) NOT NULL CHECK (nominal > 0),
    status         varchar(10)   NOT NULL DEFAULT 'pending'
                   CHECK (status IN ('pending','disetujui','dibayar','ditolak')),
    diproses_oleh  bigint        REFERENCES users(id),
    bukti_transfer text,                                -- path/URL file bukti
    created_at     timestamptz   NOT NULL DEFAULT now(),
    updated_at     timestamptz   NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 8. TRANSAKSI MITRA (nasabah belanja memakai saldo)
-- ---------------------------------------------------------------------
CREATE TABLE transaksi_mitra (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nasabah_id       bigint        NOT NULL REFERENCES nasabah(id),
    kartu_id         bigint        REFERENCES kartu(id),
    toko_mitra_id    bigint        NOT NULL REFERENCES toko_mitra(id),
    nominal          numeric(14,2) NOT NULL CHECK (nominal > 0),
    status           varchar(12)   NOT NULL DEFAULT 'berhasil'
                     CHECK (status IN ('berhasil','dibatalkan')),
    dibatalkan_oleh  bigint        REFERENCES users(id),  -- mitra atau admin
    alasan_batal     text,
    waktu_batal      timestamptz,
    pencairan_id     bigint        REFERENCES pencairan_mitra(id), -- kosong = belum dicairkan
    created_at       timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT transaksi_batal_lengkap CHECK (
        status = 'berhasil'
        OR (dibatalkan_oleh IS NOT NULL AND alasan_batal IS NOT NULL AND waktu_batal IS NOT NULL)
    )
);

-- ---------------------------------------------------------------------
-- 9. PENARIKAN (nasabah tarik ke e-wallet/bank)
--    diproses_oleh terisi jika dibantu admin (nasabah tanpa HP).
-- ---------------------------------------------------------------------
CREATE TABLE penarikan (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nasabah_id        bigint        NOT NULL REFERENCES nasabah(id),
    nominal           numeric(14,2) NOT NULL CHECK (nominal > 0),  -- dipotong dari saldo
    biaya_admin       numeric(14,2) NOT NULL DEFAULT 0 CHECK (biaya_admin >= 0), -- ditanggung nasabah
    nominal_diterima  numeric(14,2) NOT NULL CHECK (nominal_diterima >= 0),
    tujuan_tipe       varchar(10)   NOT NULL CHECK (tujuan_tipe IN ('bank','e-wallet')),
    tujuan_nomor      varchar(40)   NOT NULL,
    nama_pemilik      varchar(120),
    status            varchar(10)   NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending','berhasil','gagal')),
    diproses_oleh     bigint        REFERENCES users(id),
    ref_gateway       varchar(100),
    created_at        timestamptz   NOT NULL DEFAULT now(),
    updated_at        timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT penarikan_hitungan CHECK (nominal_diterima = nominal - biaya_admin)
);

-- ---------------------------------------------------------------------
-- 10. MUTASI SALDO (ledger) - HANYA BOLEH DITAMBAH
-- ---------------------------------------------------------------------
CREATE TABLE mutasi_saldo (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nasabah_id     bigint        NOT NULL REFERENCES nasabah(id),
    tipe           varchar(12)   NOT NULL
                   CHECK (tipe IN ('setoran','belanja','penarikan','refund','koreksi')),
    arah           varchar(6)    NOT NULL CHECK (arah IN ('kredit','debit')),
    nominal        numeric(14,2) NOT NULL CHECK (nominal > 0),
    saldo_sebelum  numeric(14,2) NOT NULL CHECK (saldo_sebelum >= 0),
    saldo_sesudah  numeric(14,2) NOT NULL CHECK (saldo_sesudah >= 0),
    ref_tipe       varchar(30),                         -- mis. 'setoran', 'transaksi_mitra', 'penarikan'
    ref_id         bigint,
    keterangan     text,
    dibuat_oleh    bigint        REFERENCES users(id),
    created_at     timestamptz   NOT NULL DEFAULT now(),
    CONSTRAINT mutasi_konsisten CHECK (
        (arah = 'kredit' AND saldo_sesudah = saldo_sebelum + nominal)
        OR (arah = 'debit' AND saldo_sesudah = saldo_sebelum - nominal)
    )
);

-- ---------------------------------------------------------------------
-- 11. AUDIT LOG - HANYA BOLEH DITAMBAH
-- ---------------------------------------------------------------------
CREATE TABLE audit_log (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id     bigint REFERENCES users(id),
    aksi        varchar(60) NOT NULL,
    tabel       varchar(60),
    data_lama   jsonb,
    data_baru   jsonb,
    created_at  timestamptz NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 12. PENGATURAN SISTEM (batas aturan bisnis, bisa diubah tanpa ngoding)
-- ---------------------------------------------------------------------
CREATE TABLE pengaturan_sistem (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    kunci        varchar(60) NOT NULL UNIQUE,
    nilai        varchar(100) NOT NULL,
    keterangan   text,
    diubah_oleh  bigint REFERENCES users(id),
    updated_at   timestamptz NOT NULL DEFAULT now()
);

INSERT INTO pengaturan_sistem (kunci, nilai, keterangan) VALUES
 ('penarikan_min',            '5000',    'Minimal penarikan (Rp)'),
 ('penarikan_maks',           '500000',  'Maksimal penarikan per transaksi (Rp)'),
 ('penarikan_maks_per_hari',  '10',      'Maksimal jumlah penarikan per hari'),
 ('penarikan_total_per_hari', '1000000', 'Maksimal total penarikan per hari (Rp)'),
 ('saldo_min_tersisa',        '10000',   'Saldo minimal yang tersisa setelah penarikan (Rp); tidak berlaku untuk belanja'),
 ('batas_batal_jam',          '24',      'Batas waktu pembatalan transaksi mitra (jam)'),
 ('pin_maks_gagal',           '3',       'Salah PIN berturut-turut sebelum akun dikunci'),
 ('pin_kunci_menit',          '30',      'Lama penguncian akun setelah salah PIN (menit) - USULAN, belum diputuskan');

-- ---------------------------------------------------------------------
-- INDEKS
-- ---------------------------------------------------------------------
CREATE INDEX ix_setoran_nasabah        ON setoran (nasabah_id, created_at DESC);
CREATE INDEX ix_detail_setoran_setoran ON detail_setoran (setoran_id);
CREATE INDEX ix_transaksi_nasabah      ON transaksi_mitra (nasabah_id, created_at DESC);
CREATE INDEX ix_transaksi_mitra        ON transaksi_mitra (toko_mitra_id, created_at DESC);
CREATE INDEX ix_transaksi_pencairan    ON transaksi_mitra (pencairan_id);
CREATE INDEX ix_pencairan_mitra        ON pencairan_mitra (toko_mitra_id, status);
CREATE INDEX ix_penarikan_nasabah      ON penarikan (nasabah_id, created_at DESC);
CREATE INDEX ix_mutasi_nasabah         ON mutasi_saldo (nasabah_id, created_at DESC);
CREATE INDEX ix_mutasi_ref             ON mutasi_saldo (ref_tipe, ref_id);
CREATE INDEX ix_audit_user             ON audit_log (user_id, created_at DESC);

-- ---------------------------------------------------------------------
-- PROTEKSI: mutasi_saldo & audit_log tidak boleh diedit/dihapus
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION tolak_ubah_hapus() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Tabel % hanya boleh ditambah (append-only)', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_mutasi_append_only
    BEFORE UPDATE OR DELETE ON mutasi_saldo
    FOR EACH ROW EXECUTE FUNCTION tolak_ubah_hapus();

CREATE TRIGGER trg_audit_append_only
    BEFORE UPDATE OR DELETE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION tolak_ubah_hapus();

-- ---------------------------------------------------------------------
-- REKONSILIASI: tampilkan nasabah yang saldonya TIDAK cocok dengan ledger.
-- Hasil view ini seharusnya selalu kosong.
-- ---------------------------------------------------------------------
CREATE VIEW v_selisih_saldo AS
SELECT n.id AS nasabah_id,
       n.kode_nasabah,
       n.saldo AS saldo_tercatat,
       COALESCE(SUM(CASE m.arah WHEN 'kredit' THEN m.nominal ELSE -m.nominal END), 0) AS saldo_dari_ledger
FROM nasabah n
LEFT JOIN mutasi_saldo m ON m.nasabah_id = n.id
GROUP BY n.id, n.kode_nasabah, n.saldo
HAVING n.saldo <> COALESCE(SUM(CASE m.arah WHEN 'kredit' THEN m.nominal ELSE -m.nominal END), 0);

