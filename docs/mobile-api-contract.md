# Aionios.NET Mobile API Contract v1

Dokumen ini adalah panduan integrasi untuk aplikasi mobile. Kontrak machine-readable resmi berada di `aionios-api-v1.openapi.json` dan dapat diimpor ke Swagger, Postman, Insomnia, atau generator client.

## 1. Base URL

| Environment | Base URL |
|---|---|
| Development | `https://dev-api.aionios.net/api/v1` |
| Staging | `https://staging-api.aionios.net/api/v1` |
| Production | `https://api.aionios.net/api/v1` |
| Laravel Herd lokal | `http://aionios.net.test/api/v1` |

Seluruh endpoint production wajib menggunakan HTTPS.

## 2. Header

Endpoint terproteksi:

```http
Accept: application/json
Authorization: Bearer <access-token>
```

Untuk operasi transaksi/action, mobile harus mengirim key unik per aksi pengguna:

```http
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

Mobile boleh mengirim correlation ID. Jika tidak dikirim, server membuatnya:

```http
X-Request-ID: 550e8400-e29b-41d4-a716-446655440001
```

Server mengembalikan `X-Request-ID` pada response sukses normal. Jangan gunakan email, token, atau data sensitif sebagai request ID.

## 3. Format response

### Success

```json
{
  "success": true,
  "message": "Operation completed.",
  "data": {}
}
```

### Collection

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 125,
    "last_page": 7
  }
}
```

### Error

```json
{
  "success": false,
  "message": "Validation failed.",
  "error": {
    "code": "VALIDATION_ERROR",
    "fields": {
      "amount": ["The amount field is required."]
    }
  }
}
```

Nominal uang dikirim sebagai string decimal, misalnya `"900000.00"`. Tanggal menggunakan `YYYY-MM-DD`, timestamp menggunakan ISO 8601, dan ID resource berupa integer.

## 4. HTTP status dan error code

| HTTP | Penggunaan |
|---|---|
| `200` | Read/update/action berhasil |
| `201` | Resource/transaksi dibuat |
| `202` | Network operation diterima dan masuk antrean |
| `401` | Token tidak ada/tidak valid |
| `403` | Role tidak memiliki permission |
| `404` | Resource tidak ditemukan/tidak dapat diakses |
| `409` | State, preview, atau idempotency conflict |
| `422` | Validation atau business rule gagal |
| `429` | Rate limit terlampaui |

Error code penting:

- `AUTH_INVALID_CREDENTIALS`, `AUTH_UNAUTHORIZED`
- `FORBIDDEN`, `VALIDATION_ERROR`, `RESOURCE_NOT_FOUND`
- `IDEMPOTENCY_CONFLICT`, `IDEMPOTENCY_REQUEST_IN_PROGRESS`
- `CUSTOMER_STATE_CONFLICT`, `CUSTOMER_HAS_OUTSTANDING`
- `ONT_NOT_AVAILABLE`, `CUSTOMER_ALREADY_HAS_ONT`, `CUSTOMER_HAS_NO_ONT`
- `PAYMENT_PREVIEW_EXPIRED`, `PAYMENT_PREVIEW_MISMATCH`, `PAYMENT_STATE_CHANGED`
- `PAYMENT_ALREADY_POSTED`, `ACCOUNTING_PERIOD_CLOSED`
- `INCOME_PREVIEW_EXPIRED`, `INCOME_PREVIEW_MISMATCH`
- `EXPENSE_ALREADY_PROCESSED`, `NETWORK_JOB_STATE_CONFLICT`

Mobile harus mengambil keputusan dari `error.code`, bukan dari `message`.

## 5. Authentication

### Login

`POST /auth/login`

```json
{
  "email": "finance@aionios.net",
  "password": "secret",
  "device_name": "Samsung SM-S928B"
}
```

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "1|...",
    "token_type": "Bearer",
    "user": {
      "id": 2,
      "name": "Admin Keuangan",
      "email": "finance@aionios.net",
      "role": "admin_keuangan",
      "permissions": ["customers.view", "payments.create"]
    }
  }
}
```

Token hanya dikirim saat login. Simpan token dengan secure storage perangkat.

### Session endpoints

| Method | Path | Keterangan |
|---|---|---|
| `GET` | `/me` | User dan permission terbaru |
| `POST` | `/auth/logout` | Revoke token perangkat aktif |
| `POST` | `/auth/logout-all` | Revoke seluruh token user |

Jika request menerima `401`, mobile harus menghapus token lokal dan kembali ke login.

## 6. Role dan permission

| Modul | Owner | Keuangan | Jaringan |
|---|:---:|:---:|:---:|
| Dashboard/customer read | ✓ | ✓ | ✓ |
| Customer lifecycle | ✓ | ✓ | ✓ |
| ONT/network | ✓ | — | ✓ |
| Billing/payment | ✓ | ✓ | — |
| Income | ✓ | ✓ | — |
| Expense create/submit | ✓ | ✓ | — |
| Expense approve/reject | ✓ | — | — |
| Owner Approval Hub (All) | ✓ | — | — |
| Notification/device/reference | ✓ | ✓ | ✓ |

Gunakan `data.permissions` dari `/me` untuk UX. Backend tetap menjadi authority untuk authorization.

## 7. Endpoint catalog

### General dan dashboard

| Method | Path | Auth | Permission |
|---|---|---:|---|
| `GET` | `/health` | Tidak | — |
| `GET` | `/mobile/dashboard` | Ya | `dashboard.view` |

### Customer

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/customers` | `customers.view` | — |
| `GET` | `/customers/{customer}` | `customers.view` | — |
| `POST` | `/customers` | `customers.manage` | — |
| `PUT` | `/customers/{customer}` | `customers.manage` | — |
| `POST` | `/customers/{customer}/change-package` | `customers.manage` | — |
| `POST` | `/customers/{customer}/activate` | `customers.manage` | Ya |
| `POST` | `/customers/{customer}/terminate` | `customers.manage` | Ya |
| `POST` | `/customers/{customer}/reactivate` | `customers.manage` | Ya |

List menerima `page`, `per_page` (maksimum 100), `search`, `status`, `package_id`, dan `sort`. Nilai sort yang didukung: `created_at`, `customer_id`, `name`, `status`; awali dengan `-` untuk descending.

Change Package:

```json
{
  "package_id": 4,
  "reason": "Permintaan upgrade bandwidth pelanggan"
}
```

*Jika dipanggil oleh Owner:* langsung mengubah paket dan sinkronisasi MikroTik (`200 OK`).
*Jika dipanggil oleh Staf:* membuat pengajuan approval Owner (`202 Accepted` dengan status `approval_pending`).

Create customer:

```json
{
  "customer_id": "AIO-000100",
  "name": "Budi Santoso",
  "phone": "081234567890",
  "address": "Alamat pelanggan",
  "package_id": 3,
  "notes": null
}
```

`customer_id` boleh dihilangkan dan akan dibuat server. Customer baru berstatus `pending`.

Aktivasi:

```json
{
  "activation_date": "2026-08-23",
  "package_id": 3,
  "ppp_profile_id": 7,
  "pppoe_username": "aio000100",
  "pppoe_password": "minimum-8-karakter",
  "ont_id": 55
}
```

`ppp_profile_id` diterima untuk kompatibilitas kontrak, tetapi profile authoritative ditentukan dari package server. Response `202` membawa customer dan network job `pending`; password PPPoE tidak pernah dikirim kembali.

Terminasi:

```json
{ "reason": "Pelanggan berhenti berlangganan" }
```

Reaktivasi:

```json
{
  "activation_date": "2026-09-01",
  "package_id": 3,
  "ont_id": 60,
  "pppoe_password": "optional-new-password",
  "notes": null
}
```

Reaktivasi ditolak dengan `CUSTOMER_HAS_OUTSTANDING` jika saldo outstanding belum nol.

### ONT

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/onts` | `onts.view` | — |
| `GET` | `/onts/suggested-id` | `onts.view` | — |
| `POST` | `/onts` | `onts.manage` | — |
| `GET` | `/onts/{ont}` | `onts.view` | — |
| `GET` | `/onts/{ont}/history` | `onts.view` | — |
| `POST` | `/customers/{customer}/ont/assign` | `onts.manage` | Ya |
| `POST` | `/customers/{customer}/ont/return` | `onts.manage` | Ya |

Suggested ONT ID (`GET /onts/suggested-id`):
Mengembalikan nomor urut ONT stabil berikutnya (contoh: `{"data": {"suggested_ont_id": "ONT-0005"}}`).

Create ONT (`POST /onts`):

```json
{
  "ont_id": "ONT-0005",
  "brand": "Huawei",
  "model": "HG8245H5",
  "serial_number": "48575443B1234567",
  "mac_address": "AA:BB:CC:DD:EE:01",
  "condition": "good",
  "notes": "ONT unit baru"
}
```

Assign:

```json
{ "ont_id": 55, "notes": "Pemasangan rumah pelanggan" }
```

Return:

```json
{
  "condition": "good",
  "status": "available",
  "notes": "Perangkat sudah diperiksa"
}
```

`condition`: `good`, `fair`, `bad`. Status return: `available`, `returned`, `damaged`, `lost`.

### Network

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/network/status` | `network.view` | — |
| `GET` | `/network/jobs` | `network.view` | — |
| `GET` | `/network/jobs/{job}` | `network.view` | — |
| `POST` | `/network/jobs/{job}/retry` | `network.retry` | Ya |
| `GET` | `/customers/{customer}/network` | `network.view` | — |
| `POST` | `/customers/{customer}/network/sync` | `network.manage` | Ya |
| `POST` | `/customers/{customer}/network/isolate` | `network.manage` | Ya |
| `POST` | `/customers/{customer}/network/unisolate` | `network.manage` | Ya |

Write network mengembalikan `202`. Mobile menampilkan status job dan melakukan polling `GET /network/jobs/{job}` sampai `success` atau `failed`.

### Billing & Invoice Adjustment

| Method | Path | Permission |
|---|---|---|
| `GET` | `/customers/{customer}/invoices` | `billing.view` |
| `GET` | `/customers/{customer}/outstanding` | `billing.view` |
| `GET` | `/invoices/{invoice}` | `billing.view` |
| `POST` | `/invoices/{invoice}/adjust` | `billing.manage` |

Adjust Invoice:

```json
{
  "subtotal": 0,
  "discount_amount": 0,
  "notes": "Peralihan pelanggan / diskon khusus"
}
```

*Jika Owner:* Langsung memperbarui nominal (otomatis `PAID` jika Rp 0) -> `200 OK`.
*Jika Staf:* Membuat pengajuan approval ke Owner -> `202 Accepted` (`approval_pending`).

### Owner Approval Hub

| Method | Path | Permission |
|---|---|---|
| `GET` | `/approvals/summary` | `approvals.view` |
| `GET` | `/approvals/invoice-adjustments` | `approvals.view` |
| `POST` | `/approvals/invoice-adjustments/{adjRequest}/approve` | `approvals.manage` |
| `POST` | `/approvals/invoice-adjustments/{adjRequest}/reject` | `approvals.manage` |
| `GET` | `/approvals/package-changes` | `approvals.view` |
| `POST` | `/approvals/package-changes/{pkgRequest}/approve` | `approvals.manage` |
| `POST` | `/approvals/package-changes/{pkgRequest}/reject` | `approvals.manage` |
| `GET` | `/approvals/reversals` | `approvals.view` |
| `POST` | `/approvals/reversals/{revRequest}/approve` | `approvals.manage` |
| `POST` | `/approvals/reversals/{revRequest}/reject` | `approvals.manage` |

Reject payload:

```json
{
  "rejection_reason": "Alasan penolakan pengajuan oleh Owner"
}
```

### Payment

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/payments` | `payments.view` | — |
| `GET` | `/payments/{payment}` | `payments.view` | — |
| `POST` | `/payments/preview` | `payments.create` | — |
| `POST` | `/payments` | `payments.create` | Ya |
| `POST` | `/payments/{payment}/reversal-request` | `payments.reversal` | Ya |

Preview:

```json
{
  "customer_id": 100,
  "payment_method": "qris",
  "cash_bank_account_id": 4
}
```

Response membawa `preview_reference`, invoice outstanding, gross amount, MDR, net settlement, dan journal preview. Preview berlaku 600 detik.

Posting:

```json
{
  "customer_id": 100,
  "payment_method": "qris",
  "cash_bank_account_id": 4,
  "preview_reference": "550e8400-e29b-41d4-a716-446655440000",
  "payment_date": "2026-08-23",
  "notes": "Pembayaran mobile"
}
```

Mobile dilarang mengirim `amount`, `custom_mdr`, `mdr_percentage`, atau daftar invoice. Server menghitung ulang seluruh nilai saat posting.

### Other income

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/incomes` | `incomes.view` | — |
| `GET` | `/incomes/{income}` | `incomes.view` | — |
| `POST` | `/incomes/preview` | `incomes.create` | — |
| `POST` | `/incomes` | `incomes.create` | Ya |

Preview:

```json
{
  "date": "2026-08-23",
  "revenue_account_id": 41,
  "description": "Biaya instalasi",
  "amount": "500000.00",
  "cash_bank_account_id": 4,
  "reference": "INSTALL-001"
}
```

Posting mengirim payload yang sama ditambah `preview_reference` dari server.

### Expense

| Method | Path | Permission | Idempotency |
|---|---|---|:---:|
| `GET` | `/expenses` | `expenses.view` | — |
| `GET` | `/expenses/{expense}` | `expenses.view` | — |
| `GET` | `/expenses/{expense}/attachment` | `expenses.view` | — |
| `POST` | `/expenses` | `expenses.create` | — |
| `POST` | `/expenses/{expense}/submit` | `expenses.create` | Ya |
| `POST` | `/expenses/{expense}/approve` | `expenses.approve` | Ya |
| `POST` | `/expenses/{expense}/reject` | `expenses.approve` | Ya |

Create draft menggunakan JSON atau `multipart/form-data` jika ada attachment:

```json
{
  "date": "2026-08-23",
  "expense_account_id": 51,
  "cash_bank_account_id": 4,
  "amount": "125000.00",
  "description": "Maintenance jaringan",
  "notes": null
}
```

Field file bernama `attachment`; tipe yang diterima JPEG, PNG, PDF dengan maksimum 5 MB. File hanya dapat diunduh melalui endpoint terautentikasi.

Workflow:

```text
POST /expenses                    -> draft
POST /expenses/{id}/submit        -> pending_approval
POST /expenses/{id}/approve       -> approved + journal
POST /expenses/{id}/reject        -> rejected
```

Reject body:

```json
{ "rejection_reason": "Bukti transaksi belum lengkap" }
```

### Notification

| Method | Path |
|---|---|
| `GET` | `/notifications` |
| `GET` | `/notifications/unread-count` |
| `POST` | `/notifications/{notification}/read` |
| `POST` | `/notifications/read-all` |

List menerima `unread=true` dan pagination standar.

### Device/push token

| Method | Path |
|---|---|
| `POST` | `/devices` |
| `PUT` | `/devices/{device}` |
| `DELETE` | `/devices/{device}` |

Register/update:

```json
{
  "device_id": "device-installation-id",
  "platform": "android",
  "push_token": "fcm-token",
  "app_version": "1.0.0"
}
```

`platform`: `android` atau `ios`. Push token dienkripsi di database dan tidak pernah dikirim kembali pada response.

### Reference data

| Method | Path | Permission | Penggunaan |
|---|---|---|---|
| `GET` | `/reference/packages` | `reference.view` | Form customer/activation |
| `GET` | `/reference/cash-bank-accounts` | `reference.view` | Payment, income, expense (termasuk linked COA asset & fallback) |
| `GET` | `/reference/asset-accounts` | `reference.view` | Asset accounts (Kas, Bank, Piutang) |
| `GET` | `/reference/revenue-accounts` | `reference.view` | Other income (revenue only) |
| `GET` | `/reference/expense-accounts` | `reference.view` | Expense (expense only) |

---

## 8. Panduan Integrasi Chart of Accounts (COA)

Fitur Chart of Accounts (COA) dirancang khusus untuk memfasilitasi transaksi finansial operasional ISP pada 4 modul utama di aplikasi mobile:
1. **Pembayaran (Payments)**
2. **Pemasukan (Other Incomes)**
3. **Pengeluaran (Expenses)**
4. **Tagihan (Billing & Invoices)**

### 8.1. Hak Akses & Keamanan (Role-Based Authorization)

Endpoint COA dilindungi secara ketat dan **hanya dapat diakses oleh role finansial**:
- **Owner (`owner`)**: Memiliki izin penuh `coa.view`
- **Admin Keuangan (`admin_keuangan`)**: Memiliki izin penuh `coa.view`
- **Admin Jaringan (`admin_jaringan`)**: **Tidak memiliki akses** (Server mengembalikan `403 FORBIDDEN`)

### 8.2. Matriks Transaksi Finansial & Relasi Akun COA

| Modul Mobile | Tipe Akun Terlibat | Akun COA Standar ISP | Posisi Jurnal | Catatan Penggunaan di Mobile |
|---|---|---|---|---|
| **Pembayaran**<br>*(Payment)* | `asset`, `expense` | • `1110` Kas Kasir Utama<br>• `1120` Bank BCA Operasional<br>• `1130` Bank BRI Penerimaan<br>• `1140` QRIS Settlement Merchant<br>• `1210` Piutang Usaha Pelanggan<br>• `5170` Beban MDR QRIS | • Kas/Bank (**Debit**)<br>• Beban MDR QRIS (**Debit** jika QRIS)<br>• Piutang Usaha (**Kredit**) | Dipanggil saat user menerima pembayaran tagihan dari pelanggan (Manual Tunai/Transfer atau QRIS). |
| **Pemasukan**<br>*(Other Income)* | `revenue`, `asset` | • `4110` Pendapatan Langganan<br>• `4210` Pendapatan Instalasi & Lain<br>• `1110`-`1140` Kas/Bank | • Kas/Bank (**Debit**)<br>• Pendapatan (**Kredit**) | Digunakan pada form input pemasukan non-tagihan (biaya pasang baru, penjualan perangkat ONT/router, jasa maintenance). |
| **Pengeluaran**<br>*(Expense)* | `expense`, `asset` | • `5110` Beban Bandwidth & Upstream<br>• `5120` Beban Listrik & POP Shelter<br>• `5130` Beban Gaji & Tim<br>• `5140` Beban Pemeliharaan Kabel<br>• `5150` Beban Bensin & Transport<br>• `5160` Beban Sewa Tiang FO<br>• `5180` Beban Operasional Lain<br>• `1110`-`1140` Kas/Bank | • Beban Operasional (**Debit**)<br>• Kas/Bank (**Kredit**) | Digunakan saat teknisi/admin membuat voucher pengeluaran operasional di lapangan. |
| **Tagihan**<br>*(Billing/Invoices)* | `asset`, `revenue` | • `1210` Piutang Usaha Pelanggan<br>• `4110` Pendapatan Langganan Internet | • Piutang Usaha (**Debit**)<br>• Pendapatan Internet (**Kredit**) | Referensi saat generate invoice, adjustment tagihan, dan rekonsiliasi status tagihan pelanggan. |

### 8.3. Spesifikasi Endpoint COA

| Method | Path | Permission | Penggunaan |
|---|---|---|---|
| `GET` | `/chart-of-accounts` (alias: `/coas`) | `coa.view` | Daftar lengkap COA dengan filter kontekstual transaksi |
| `GET` | `/chart-of-accounts/{id}` (alias: `/coas/{id}`) | `coa.view` | Detail satu akun COA beserta relasi Kas/Bank |

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
   Authorization: Bearer <token>
   ```
   *Mengembalikan seluruh akun Pendapatan (`type=revenue`) untuk pilihan kategori pemasukan, dan akun Kas/Bank (`type=asset`) untuk tujuan setor dana.*

2. **Untuk Form Pengeluaran (Expense):**
   ```http
   GET /api/v1/chart-of-accounts?usage=expense HTTP/1.1
   Authorization: Bearer <token>
   ```
   *Mengembalikan seluruh akun Beban (`type=expense`) untuk pilihan jenis pengeluaran, dan akun Kas/Bank sumber dana.*

3. **Untuk Form Pembayaran (Payment):**
   ```http
   GET /api/v1/chart-of-accounts?usage=payment HTTP/1.1
   Authorization: Bearer <token>
   ```

4. **Pencarian Cepat (Auto-Complete Search):**
   ```http
   GET /api/v1/chart-of-accounts?search=bensin HTTP/1.1
   Authorization: Bearer <token>
   ```

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
    }
  ]
}
```

### 8.4. Rekomendasi Best Practice untuk Mobile Developer

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

---

## 9. Idempotency behavior

Mobile membuat satu UUID baru ketika user memulai aksi dan mempertahankan UUID yang sama selama retry otomatis.

- Key sama + payload sama: server mengembalikan response awal dan header `Idempotent-Replayed: true`.
- Key sama + payload berbeda: `409 IDEMPOTENCY_CONFLICT`.
- Request awal masih diproses: `409 IDEMPOTENCY_REQUEST_IN_PROGRESS`.
- Jangan memakai ulang key untuk aksi pengguna yang berbeda.

## 10. Alur integrasi mobile

### Startup

1. Ambil token dari secure storage.
2. Panggil `GET /me`.
3. Jika `200`, simpan user dan permission di application state.
4. Jika `401`, hapus token dan buka halaman login.
5. Ambil reference data sesuai fitur yang dapat diakses.

### Payment

1. Ambil outstanding customer.
2. Panggil payment preview.
3. Tampilkan nilai server kepada user.
4. Setelah konfirmasi perangkat, kirim payment posting dengan preview reference dan Idempotency-Key.
5. Jangan menghitung atau mengubah amount/MDR di mobile.

### Network operation

1. Kirim action dengan Idempotency-Key.
2. Terima `202` dan `job.id`.
3. Poll job dengan interval wajar.
4. Hentikan polling pada `success` atau `failed`.

## 11. Dokumentasi dan client generation

- Scramble UI lokal: `/docs/api`
- OpenAPI JSON runtime: `/docs/api.json`
- OpenAPI versioned di repository: `docs/aionios-api-v1.openapi.json`

Jika contract berubah, perbarui controller/Form Request/API Resource, jalankan seluruh test, kemudian ekspor ulang OpenAPI versioned. Jangan mengubah file JSON hasil export secara manual.
