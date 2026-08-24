# Panduan Integrasi COA (Chart of Accounts) untuk Mobile Application Aionios.NET

Dokumen ini adalah panduan teknis implementasi dan integrasi **Chart of Accounts (COA)** pada aplikasi mobile Aionios.NET. Fitur ini dirancang khusus untuk memfasilitasi transaksi finansial operasional ISP pada 4 modul utama:
1. **Pembayaran (Payments)**
2. **Pemasukan (Other Incomes)**
3. **Pengeluaran (Expenses)**
4. **Tagihan (Billing & Invoices)**

---

## 1. Hak Akses & Keamanan (Role-Based Authorization)

Endpoint COA dilindungi secara ketat dan **hanya dapat diakses oleh role finansial**:
- **Owner (`owner`)**: Memiliki izin penuh `coa.view`
- **Admin Keuangan (`admin_keuangan`)**: Memiliki izin penuh `coa.view`
- **Admin Jaringan (`admin_jaringan`)**: **Tidak memiliki akses** (Server mengembalikan `403 FORBIDDEN`)

### Header Wajib:
```http
Accept: application/json
Authorization: Bearer <access_token>
```

---

## 2. Matriks Transaksi & Relasi Akun COA

Berikut adalah matriks keterhubungan modul transaksi di aplikasi mobile dengan akun COA di sistem akuntansi:

| Modul Mobile | Tipe Akun Terlibat | Akun COA Standar ISP | Posisi Jurnal | Catatan Penggunaan di Mobile |
|---|---|---|---|---|
| **Pembayaran**<br>*(Payment)* | `asset`, `expense` | • `1110` Kas Kasir Utama<br>• `1120` Bank BCA Operasional<br>• `1130` Bank BRI Penerimaan<br>• `1140` QRIS Settlement Merchant<br>• `1210` Piutang Usaha Pelanggan<br>• `5170` Beban MDR QRIS | • Kas/Bank (**Debit**)<br>• Beban MDR QRIS (**Debit** jika QRIS)<br>• Piutang Usaha (**Kredit**) | Dipanggil saat user menerima pembayaran tagihan dari pelanggan (Manual Tunai/Transfer atau QRIS). |
| **Pemasukan**<br>*(Other Income)* | `revenue`, `asset` | • `4110` Pendapatan Langganan<br>• `4210` Pendapatan Instalasi & Lain<br>• `1110`-`1140` Kas/Bank | • Kas/Bank (**Debit**)<br>• Pendapatan (**Kredit**) | Digunakan pada form input pemasukan non-tagihan (biaya pasang baru, penjualan perangkat ONT/router, jasa maintenance). |
| **Pengeluaran**<br>*(Expense)* | `expense`, `asset` | • `5110` Beban Bandwidth & Upstream<br>• `5120` Beban Listrik & POP Shelter<br>• `5130` Beban Gaji & Tim<br>• `5140` Beban Pemeliharaan Kabel<br>• `5150` Beban Bensin & Transport<br>• `5160` Beban Sewa Tiang FO<br>• `5180` Beban Operasional Lain<br>• `1110`-`1140` Kas/Bank | • Beban Operasional (**Debit**)<br>• Kas/Bank (**Kredit**) | Digunakan saat teknisi/admin membuat voucher pengeluaran operasional di lapangan. |
| **Tagihan**<br>*(Billing/Invoices)* | `asset`, `revenue` | • `1210` Piutang Usaha Pelanggan<br>• `4110` Pendapatan Langganan Internet | • Piutang Usaha (**Debit**)<br>• Pendapatan Internet (**Kredit**) | Referensi saat generate invoice, adjustment tagihan, dan rekonsiliasi status tagihan pelanggan. |

---

## 3. Spesifikasi Endpoint API

### 3.1. List Chart of Accounts
`GET /api/v1/chart-of-accounts` (atau alias `GET /api/v1/reference/chart-of-accounts` / `GET /api/v1/coas`)

#### Query Parameters:

| Parameter | Tipe | Contoh | Keterangan |
|---|---|---|---|
| `usage` / `for` | string | `payment`, `income`, `expense`, `billing`, `cash_bank` | Filter instan sesuai form transaksi di mobile (sangat direkomendasikan). |
| `type` | string | `revenue` atau `revenue,expense` | Filter berdasarkan tipe: `asset`, `liability`, `equity`, `revenue`, `expense`. |
| `category` | string | `Kas & Setara Kas` | Filter berdasarkan kategori akun. |
| `search` / `q` | string | `QRIS` atau `5110` | Pencarian teks pada kode, nama, atau kategori. |
| `is_active` | string / bool | `true` (default), `false`, `all` | Filter status keaktifan akun. |
| `per_page` | integer | `20` | Mengaktifkan format pagination jika diisi. |
| `sort` | string | `code`, `-code`, `name`, `-name` | Pengurutan data (default: `code` ascending). |

#### Contoh Request berdasarkan Form di Mobile:

1. **Untuk Form Pemasukan (Other Income):**
   ```http
   GET /api/v1/chart-of-accounts?usage=income HTTP/1.1
   Host: api.aionios.net
   Authorization: Bearer <token>
   ```
   *Mengembalikan seluruh akun Pendapatan (`type=revenue`) untuk pilihan kategori pemasukan, dan akun Kas/Bank (`type=asset`) untuk tujuan setor dana.*

2. **Untuk Form Pengeluaran (Expense):**
   ```http
   GET /api/v1/chart-of-accounts?usage=expense HTTP/1.1
   Host: api.aionios.net
   Authorization: Bearer <token>
   ```
   *Mengembalikan seluruh akun Beban (`type=expense`) untuk pilihan jenis pengeluaran, dan akun Kas/Bank sumber dana.*

3. **Untuk Form Pembayaran (Payment):**
   ```http
   GET /api/v1/chart-of-accounts?usage=payment HTTP/1.1
   Host: api.aionios.net
   Authorization: Bearer <token>
   ```

4. **Pencarian Cepat (Auto-Complete Search):**
   ```http
   GET /api/v1/chart-of-accounts?search=bensin HTTP/1.1
   Host: api.aionios.net
   Authorization: Bearer <token>
   ```

---

#### Contoh Response Sukses (200 OK):

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "1110",
      "name": "Kas Kasir Utama",
      "type": "asset",
      "category": "Kas & Setara Kas",
      "normal_balance": "debit",
      "is_active": true,
      "is_system": true,
      "cash_bank_accounts": [
        {
          "id": 1,
          "name": "Kas Tunai Kasir",
          "bank_name": "Internal Cash",
          "account_number": "CASH-01",
          "current_balance": "5000000.00",
          "is_active": true
        }
      ],
      "created_at": "2026-08-24T00:00:00.000000Z",
      "updated_at": "2026-08-24T00:00:00.000000Z"
    },
    {
      "id": 11,
      "code": "4110",
      "name": "Pendapatan Langganan Internet",
      "type": "revenue",
      "category": "Pendapatan Usaha",
      "normal_balance": "credit",
      "is_active": true,
      "is_system": true,
      "cash_bank_accounts": [],
      "created_at": "2026-08-24T00:00:00.000000Z",
      "updated_at": "2026-08-24T00:00:00.000000Z"
    },
    {
      "id": 13,
      "code": "5110",
      "name": "Beban Bandwidth & Upstream",
      "type": "expense",
      "category": "Beban Pokok Operasional",
      "normal_balance": "debit",
      "is_active": true,
      "is_system": true,
      "cash_bank_accounts": [],
      "created_at": "2026-08-24T00:00:00.000000Z",
      "updated_at": "2026-08-24T00:00:00.000000Z"
    }
  ]
}
```

---

### 3.2. Detail Chart of Account
`GET /api/v1/chart-of-accounts/{id}`

#### Contoh Response Sukses (200 OK):
```json
{
  "success": true,
  "data": {
    "id": 2,
    "code": "1120",
    "name": "Bank BCA Operasional",
    "type": "asset",
    "category": "Kas & Setara Kas",
    "normal_balance": "debit",
    "is_active": true,
    "is_system": true,
    "cash_bank_accounts": [
      {
        "id": 2,
        "name": "BCA Bisnis Operasional",
        "bank_name": "Bank Central Asia",
        "account_number": "8830-192-881",
        "current_balance": "45000000.00",
        "is_active": true
      }
    ],
    "created_at": "2026-08-24T00:00:00.000000Z",
    "updated_at": "2026-08-24T00:00:00.000000Z"
  }
}
```

---

## 4. Penanganan Error HTTP

| Status Code | Error Code | Penyebab | Tindakan Mobile Client |
|---|---|---|---|
| `401 Unauthorized` | `AUTH_UNAUTHORIZED` | Token tidak valid atau sesi berakhir. | Redirect ke layar Login dan bersihkan secure storage. |
| `403 Forbidden` | `FORBIDDEN` | Akun yang login bukan `owner` atau `admin_keuangan` (contoh: `admin_jaringan`). | Tampilkan notifikasi "Akses Finansial Terbatas". Sembunyikan menu COA / keuangan. |
| `404 Not Found` | `RESOURCE_NOT_FOUND` | ID akun COA tidak ditemukan. | Perbarui daftar cache akun lokal. |
| `422 Unprocessable`| `VALIDATION_ERROR` | Format filter tidak sesuai (misal: tipe sorting salah). | Tinjau parameter query yang dikirimkan. |

---

## 5. Rekomendasi Best Practice untuk Mobile Developer

1. **Local Caching**:
   Data COA relatif jarang berubah. Simpan data COA di SQLite / Hive / Room / CoreData lokal saat startup aplikasi setelah login berhasil, dan lakukan sinkronisasi berkala (misal 1x sehari atau tombol Refresh manual).

2. **Pengelompokan Dropdown UI (Grouping)**:
   Pada form Pengeluaran dan Pemasukan, kelompokkan akun dropdown berdasarkan field `category` (misalnya: *Beban Operasional*, *Beban Pokok*, *Pendapatan Lain*).

3. **Format Tampilan Dropdown**:
   Rekomendasi format tampilan item di dropdown aplikasi:
   `[CODE] - [NAME]`
   Contoh: `5150 - Beban Transportasi & Kendaraan Tim`

4. **Integrasi Form Transaksi**:
   - Untuk `POST /incomes` (Pemasukan): Kirim `revenue_account_id` yang dipilih dari COA bertipe `revenue`.
   - Untuk `POST /expenses` (Pengeluaran): Kirim `expense_account_id` yang dipilih dari COA bertipe `expense`.
   - Untuk `POST /payments` (Pembayaran): Kirim `cash_bank_account_id` yang diperoleh dari `cash_bank_accounts` pada COA bertipe `asset`.
