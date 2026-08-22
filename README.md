# Aionios.NET
**Sistem Billing Terintegrasi & Pembukuan Akuntansi Berpasangan ISP (Internet Service Provider)**

Aionios.NET adalah platform operasional ISP dan manajemen keuangan end-to-end yang dibangun dengan standar enterprise. Mengintegrasikan manajemen pelanggan internet, sinkronisasi MikroTik RouterOS 7.24 (dengan antrean sinkronisasi offline aman), siklus billing otomatis, pembayaran QRIS dengan kalkulasi MDR, serta sistem akuntansi pembukuan berpasangan (double-entry bookkeeping) yang patuh PSAK.

---

## 🚀 Fitur Utama

### 1. Manajemen Pelanggan & Paket Internet
- Database pelanggan dengan riwayat status lengkap (Aktif, Isolir, Putus Berlangganan).
- Manajemen paket internet bertingkat & sistem diskon/promo berbatas waktu.
- Pelacakan inventori modem ONT dan riwayat perpindahan perangkat.

### 2. Integrasi MikroTik RouterOS (v7.24 Stable)
- Sinkronisasi REST / API Socket ke Router MikroTik.
- Manajemen PPPoE Secrets & profil isolir/unisolir otomatis.
- **Offline Network Jobs Queue**: Jika router mati/koneksi putus, seluruh perintah aktivasi dan isolir tersimpan di antrean dan otomatis dieksekusi saat koneksi pulih.

### 3. Billing & Penagihan Otomatis
- Pembuatan tagihan massal otomatis setiap tanggal 1 (termasuk kalkulasi prorata untuk aktivasi pertengahan bulan).
- Jatuh tempo tanggal 22 & eksekusi isolir otomatis tanggal 23 pukul 01:00 WIB.
- Konfirmasi pembayaran manual & QRIS dengan auto-reconciliation dan pencatatan potongan MDR.

### 4. Akuntansi Double-Entry & Laporan Keuangan Standar
- Bagan Akun Standar (Chart of Accounts / COA) dengan hierarki 4 digit.
- Pembuatan ayat jurnal umum otomatis (Real-Time Journal Posting) untuk setiap transaksi billing, beban operasional, penerimaan kas, dan modal.
- Buku Besar (General Ledger) dengan saldo berjalan pergerakan debit/kredit.
- Neraca Saldo (Trial Balance) dan Kunci Periode Akuntansi.
- 6 Laporan Keuangan Standar:
  1. **Laporan Laba Rugi (Income Statement)**
  2. **Neraca Keuangan (Balance Sheet)**
  3. **Laporan Arus Kas Langsung (Direct Cash Flow)**
  4. **Laporan Perubahan Modal (Equity Changes)**
  5. **Laporan Umur Piutang (Aging Receivables)**
  6. **Laporan Rekonsiliasi Pendapatan & MDR QRIS**

### 5. Keamanan, Governance & Audit Trail
- 3 Role Akun Pengguna: **Owner / Super Admin**, **Admin Jaringan**, **Admin Keuangan**.
- Workflow Persetujuan (Approval) khusus Owner untuk pengeluaran besar dan reversal pembayaran.
- Catatan audit menyeluruh (*immutable audit trail*) untuk semua aksi krusial.

---

## 🛠️ Tech Stack

- **Backend**: Laravel 13 (PHP 8.2+)
- **Frontend**: Svelte 5 (Runes Architecture) + Inertia.js v3
- **Styling**: TailwindCSS & Custom **Light Stone Design System** (Warm stone neutrals, crisp white surfaces, high-contrast typography)
- **UI Components**: Shadcn-Svelte architecture
- **Database**: MySQL / SQLite
- **Icons**: Lucide Svelte

---

## 📦 Panduan Instalasi & Menjalankan Aplikasi

1. **Clone repositori**:
   ```bash
   git clone https://github.com/ArajaID/Aionios.NET.git
   cd Aionios.NET
   ```

2. **Install PHP & Node Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Migrasi Database & Seed Data Demo**:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Build Assets & Jalankan Server**:
   ```bash
   npm run build
   php artisan serve
   ```
   Aplikasi akan berjalan di `http://127.0.0.1:8000`.

---

## 🔑 Akun Demo Login

| Role | Email | Password |
|---|---|---|
| **Owner / Super Admin** | `owner@aionios.net` | `password` |
| **Admin Jaringan** | `jaringan@aionios.net` | `password` |
| **Admin Keuangan** | `keuangan@aionios.net` | `password` |

---

## 📄 Lisensi
Hak Cipta © 2026 PT Aionios Solusi Telematika. Seluruh hak cipta dilindungi undang-undang.
