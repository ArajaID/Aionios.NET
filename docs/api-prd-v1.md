# Product Requirements Document (PRD) — Aionios.NET Laravel API v1

**Versi:** 1.0  
**Status:** Backend API Specification  
**Platform:** Laravel REST API  
**API Version:** `/api/v1`  
**Database:** MariaDB  
**API Documentation:** Scramble / OpenAPI Documentation  
**Consumers:** Aionios.NET Mobile Flutter, Aionios.NET Web, internal integrations  
**Deployment:** VPS Production  
**Primary Objective:** Secure, auditable, versioned API for Aionios.NET Mobile and internal clients

---

# 1. Ringkasan

Aionios.NET API v1 adalah REST API resmi yang menjadi penghubung antara aplikasi client dan seluruh domain backend Aionios.NET.

API bertanggung jawab atas:

- authentication;
- authorization;
- RBAC;
- customer operations;
- ONT operations;
- PPPoE/network operations;
- billing data;
- payment processing;
- pemasukan;
- pengeluaran;
- approval;
- notification;
- audit trail;
- idempotency;
- financial integrity.

Arsitektur:

**Flutter / Web Client**

→ HTTPS

→ **Laravel API v1**

→ Domain Services

→ MariaDB / Queue / MikroTik Integration

Client tidak diperbolehkan:

- mengakses MariaDB langsung;
- mengakses MikroTik langsung;
- menghitung journal sebagai source of truth;
- menentukan MDR sendiri;
- mengubah invoice secara lokal;
- menentukan authorization hanya melalui UI.

---

# 2. Tujuan API

API v1 harus:

1. menyediakan contract stabil untuk mobile;
2. menjaga business rules di backend;
3. menggunakan versioning;
4. memiliki authentication yang aman;
5. menerapkan server-side authorization;
6. mempunyai struktur response konsisten;
7. menangani duplicate request;
8. menjaga transaction atomicity;
9. mendukung audit trail;
10. menyediakan documentation otomatis melalui Scramble;
11. mudah diuji;
12. memiliki observability;
13. tidak mengekspos informasi sensitif;
14. menjaga financial integrity.

---

# 3. API Base URL

Environment:

### Development

`https://dev-api.aionios.net/api/v1`

### Staging

`https://staging-api.aionios.net/api/v1`

### Production

`https://api.aionios.net/api/v1`

Jika API berada pada host utama:

`https://aionios.net/api/v1`

Keputusan hostname final mengikuti deployment infrastructure.

Semua production endpoint wajib menggunakan HTTPS.

---

# 4. API Versioning

Versioning menggunakan URL prefix:

`/api/v1`

Contoh:

`GET /api/v1/customers`

Perubahan breaking tidak boleh mengubah contract v1 secara diam-diam.

Breaking change harus menggunakan versi baru:

`/api/v2`

Non-breaking changes masih dapat dilakukan pada v1, misalnya:

- field response optional baru;
- endpoint baru;
- filter tambahan.

---

# 5. API Consumer

API v1 digunakan oleh:

### Flutter Mobile

Consumer utama MVP.

### Aionios.NET Web

Dapat menggunakan API yang sama selama payload sesuai.

### Internal Jobs

Backend service tidak harus melalui HTTP jika berada pada application domain yang sama.

### Future Integration

Integration baru harus menggunakan dedicated credentials/scopes jika diperlukan.

---

# 6. API Architecture

Laravel disarankan menggunakan struktur:

```text
app/
├── Domain/
│   ├── Customers/
│   ├── Billing/
│   ├── Payments/
│   ├── Network/
│   ├── ONT/
│   ├── Accounting/
│   ├── Income/
│   ├── Expenses/
│   ├── Approvals/
│   └── Notifications/
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/
│   └── Resources/
│       └── Api/
│           └── V1/
│
├── Actions/
├── Services/
├── Policies/
├── Jobs/
└── Support/
```

Controller harus tipis.

Business rules ditempatkan pada:

- Actions;
- Services;
- Domain layer;
- Policies.

---

# 7. API Route Structure

Contoh:

```php
Route::prefix('v1')
    ->group(function () {
        // API v1 routes
    });
```

Authenticated routes dikelompokkan menggunakan authentication middleware.

Contoh:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // protected API
});
```

Authentication implementation final dapat menggunakan Laravel Sanctum atau strategi token resmi lain yang diputuskan tim.

---

# 8. Authentication Strategy

Recommended baseline:

**Laravel Sanctum personal/access token based authentication untuk mobile API.**

Login:

`POST /api/v1/auth/login`

Request:

```json
{
  "email": "admin@aionios.net",
  "password": "********",
  "device_name": "Samsung SM-S928B"
}
```

Response sukses:

```json
{
  "success": true,
  "data": {
    "token": "...",
    "token_type": "Bearer",
    "user": {
      "id": 12,
      "name": "Admin",
      "email": "admin@aionios.net",
      "role": "admin_finance"
    }
  }
}
```

---

# 9. Token Storage

Laravel hanya menghasilkan token.

Flutter bertanggung jawab menyimpan token menggunakan secure storage perangkat.

Token tidak boleh:

- ditaruh di SharedPreferences biasa;
- dimasukkan ke source code;
- ditulis ke application log;
- dikirim ke analytics.

---

# 10. Logout

Endpoint:

`POST /api/v1/auth/logout`

Logout harus:

- revoke token aktif;
- unregister atau deactivate push token terkait session/device bila diperlukan;
- mencatat audit event.

Optional:

`POST /api/v1/auth/logout-all`

untuk mencabut seluruh device session milik user.

---

# 11. Current User Endpoint

Endpoint:

`GET /api/v1/me`

Response minimal:

```json
{
  "success": true,
  "data": {
    "id": 12,
    "name": "Admin Keuangan",
    "email": "finance@aionios.net",
    "role": "admin_finance",
    "permissions": [
      "customers.view",
      "payments.create",
      "income.create",
      "expenses.create"
    ]
  }
}
```

Flutter menggunakan permission response untuk UX.

Backend tetap melakukan authorization ulang setiap request.

---

# 12. Server-Side RBAC

API tidak boleh bergantung pada menu yang tersembunyi di Flutter.

Setiap endpoint sensitif menggunakan:

- Policy;
- Gate;
- Permission middleware;
- atau kombinasi yang konsisten.

Contoh:

Admin Jaringan mengirim:

`POST /api/v1/payments`

Expected:

`403 Forbidden`

walaupun request dibuat manual menggunakan Postman.

---

# 13. Roles

Minimal:

- Owner / Super Admin
- Admin Keuangan
- Admin Jaringan

Permission granular dianjurkan.

Contoh:

- `customers.view`
- `customers.activate`
- `customers.terminate`
- `network.view`
- `network.retry`
- `payments.create`
- `income.create`
- `expenses.create`
- `expenses.approve`
- `reversals.approve`

---

# 14. Standard Response Format

Semua endpoint menggunakan envelope yang konsisten.

Success:

```json
{
  "success": true,
  "message": "Customer retrieved successfully.",
  "data": {}
}
```

Collection:

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

Error:

```json
{
  "success": false,
  "message": "Validation failed.",
  "error": {
    "code": "VALIDATION_ERROR",
    "fields": {
      "amount": [
        "The amount field is required."
      ]
    }
  }
}
```

---

# 15. Error Codes

Gunakan machine-readable error code.

Contoh:

- `AUTH_INVALID_CREDENTIALS`
- `AUTH_TOKEN_EXPIRED`
- `AUTH_UNAUTHORIZED`
- `FORBIDDEN`
- `VALIDATION_ERROR`
- `RESOURCE_NOT_FOUND`
- `CUSTOMER_NOT_ACTIVE`
- `CUSTOMER_HAS_OUTSTANDING`
- `PAYMENT_ALREADY_POSTED`
- `PAYMENT_AMOUNT_MISMATCH`
- `ACCOUNTING_PERIOD_CLOSED`
- `NETWORK_SYNC_PENDING`
- `NETWORK_OPERATION_FAILED`
- `EXPENSE_ALREADY_PROCESSED`
- `APPROVAL_ALREADY_PROCESSED`
- `IDEMPOTENCY_CONFLICT`

Flutter tidak boleh bergantung pada human-readable message untuk business decision.

---

# 16. HTTP Status Convention

Gunakan status standar:

`200 OK`

Successful GET/update/action.

`201 Created`

Resource berhasil dibuat.

`202 Accepted`

Request diterima dan akan diproses asynchronous.

`204 No Content`

Action berhasil tanpa response body jika digunakan.

`400 Bad Request`

Request business-invalid tertentu.

`401 Unauthorized`

Authentication tidak valid.

`403 Forbidden`

Tidak memiliki permission.

`404 Not Found`

Resource tidak ditemukan.

`409 Conflict`

State conflict/idempotency conflict.

`422 Unprocessable Entity`

Validation/business validation.

`429 Too Many Requests`

Rate limit.

`500 Internal Server Error`

Unexpected backend error.

---

# 17. Pagination

Semua list besar menggunakan server-side pagination.

Parameters:

`?page=1&per_page=20`

Maximum `per_page` harus dibatasi backend.

Contoh maximum:

`100`

Mobile default dianjurkan:

`20`

---

# 18. Filtering

Contoh:

`GET /api/v1/customers?status=active`

`GET /api/v1/payments?method=qris`

`GET /api/v1/network/jobs?status=failed`

`GET /api/v1/expenses?status=pending_approval`

Filter harus whitelist.

Client tidak boleh dapat memasukkan arbitrary column/database expression.

---

# 19. Sorting

Contoh:

`?sort=-created_at`

Prefix `-` berarti descending.

Allowed sort fields harus whitelist.

Contoh:

- created_at;
- customer_id;
- amount;
- status.

---

# 20. Search

Search endpoint/list menggunakan query:

`?search=Budi`

Customer search minimal mencakup:

- Customer ID;
- name;
- phone;
- PPPoE username.

Search harus menggunakan parameter binding.

---

# 21. Authentication Endpoints

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/logout-all
GET  /api/v1/me
```

Optional future:

```text
GET    /api/v1/devices
DELETE /api/v1/devices/{device}
```

---

# 22. Dashboard API

Endpoint:

`GET /api/v1/mobile/dashboard`

Backend menentukan response berdasarkan role.

Contoh Owner:

```json
{
  "success": true,
  "data": {
    "finance": {},
    "customers": {},
    "network": {},
    "approvals": {}
  }
}
```

Jangan mengirim data dashboard yang user tidak berhak lihat.

---

# 23. Customer Endpoints

```text
GET  /api/v1/customers
GET  /api/v1/customers/{customer}
POST /api/v1/customers
PUT  /api/v1/customers/{customer}

POST /api/v1/customers/{customer}/activate
POST /api/v1/customers/{customer}/terminate
POST /api/v1/customers/{customer}/reactivate
```

Tidak disediakan:

`DELETE /customers/{customer}`

untuk customer yang mempunyai lifecycle/history.

---

# 24. Customer Detail Response

Minimal:

```json
{
  "id": 100,
  "customer_id": "AIO-000100",
  "name": "Budi Santoso",
  "phone": "08xxxxxxxxxx",
  "address": "...",
  "status": "active",
  "package": {},
  "pppoe": {},
  "ont": {},
  "billing": {
    "outstanding": "0.00"
  }
}
```

Nominal monetary lebih aman direpresentasikan sebagai string decimal pada JSON agar tidak kehilangan precision pada consumer tertentu.

---

# 25. Customer Activation Endpoint

`POST /api/v1/customers/{customer}/activate`

Request:

```json
{
  "activation_date": "2026-08-23",
  "package_id": 3,
  "ppp_profile_id": 7,
  "pppoe_username": "aio000100",
  "pppoe_password": "********",
  "ont_id": 55
}
```

Backend:

1. authorize;
2. validate customer state;
3. validate package;
4. validate ONT availability;
5. create activation records;
6. commit local transaction;
7. queue MikroTik command;
8. audit;
9. return local + network state.

---

# 26. Activation Response

```json
{
  "success": true,
  "data": {
    "customer_status": "active",
    "network": {
      "status": "pending",
      "job_id": 123
    }
  }
}
```

Network timeout tidak boleh membatalkan valid local transaction setelah commit sesuai business rule yang ditetapkan.

---

# 27. Reactivation Rule

Endpoint:

`POST /api/v1/customers/{customer}/reactivate`

Backend wajib memverifikasi:

`Outstanding Balance = 0`

Jika tidak:

```json
{
  "success": false,
  "message": "Customer still has outstanding balance.",
  "error": {
    "code": "CUSTOMER_HAS_OUTSTANDING"
  }
}
```

HTTP:

`422`

atau `409` sesuai API convention final.

---

# 28. ONT Endpoints

```text
GET  /api/v1/onts
GET  /api/v1/onts/{ont}

POST /api/v1/customers/{customer}/ont/assign
POST /api/v1/customers/{customer}/ont/return

GET  /api/v1/onts/{ont}/history
```

---

# 29. ONT Assignment

Backend wajib memverifikasi:

- ONT exists;
- status Available;
- belum assigned ke customer lain;
- customer valid;
- permission user.

Gunakan database transaction.

Concurrency conflict harus dicegah.

---

# 30. Network Endpoints

```text
GET  /api/v1/network/status
GET  /api/v1/network/jobs
GET  /api/v1/network/jobs/{job}

POST /api/v1/network/jobs/{job}/retry

GET  /api/v1/customers/{customer}/network
POST /api/v1/customers/{customer}/network/sync
POST /api/v1/customers/{customer}/network/isolate
POST /api/v1/customers/{customer}/network/unisolate
```

---

# 31. MikroTik Isolation Rule

API tidak melakukan disable PPP Secret untuk billing isolation.

Backend menjalankan:

`PPP Profile → ISOLIR`

Restore priority:

1. active promo profile;
2. normal package profile.

Flutter tidak menentukan restore profile.

---

# 32. Network Command Architecture

Endpoint yang memicu MikroTik tidak berkomunikasi secara blocking jika tidak diperlukan.

Pattern:

Client Request

→ Validate

→ Create Network Job

→ Queue

→ Response

→ Worker

→ MikroTik

State:

- Pending
- Processing
- Success
- Failed
- Manual Retry Required

---

# 33. Billing Endpoints

Mobile bersifat read-focused untuk billing.

```text
GET /api/v1/customers/{customer}/invoices
GET /api/v1/customers/{customer}/outstanding
GET /api/v1/invoices/{invoice}
```

Mobile tidak dapat:

- membuat recurring invoice manual;
- mengedit invoice posted;
- menghapus invoice.

Billing generation tetap scheduler/backend concern.

---

# 34. Payment Endpoints

```text
POST /api/v1/payments/preview
POST /api/v1/payments

GET  /api/v1/payments
GET  /api/v1/payments/{payment}

POST /api/v1/payments/{payment}/reversal-request
```

---

# 35. Payment Preview

Request:

```json
{
  "customer_id": 100,
  "payment_method": "qris",
  "cash_bank_account_id": 4
}
```

Backend mengambil seluruh outstanding.

Client tidak mengirim authoritative total.

Response:

```json
{
  "success": true,
  "data": {
    "customer": {},
    "invoices": [],
    "gross_amount": "900000.00",
    "mdr_percentage": "0.70",
    "mdr_amount": "6300.00",
    "net_settlement": "893700.00",
    "cash_bank_account": {},
    "journal_preview": []
  }
}
```

---

# 36. Payment Posting

Endpoint:

`POST /api/v1/payments`

Request menggunakan server-generated preview token atau equivalent validation strategy.

Contoh:

```json
{
  "customer_id": 100,
  "payment_method": "qris",
  "cash_bank_account_id": 4,
  "preview_reference": "..."
}
```

Backend wajib menghitung ulang state sebelum commit.

Jangan mempercayai:

- amount dari Flutter;
- MDR dari Flutter;
- invoice list dari Flutter;
- journal preview dari Flutter.

---

# 37. Payment Transaction Atomicity

Payment posting:

1. authorize;
2. begin DB transaction;
3. lock/revalidate outstanding;
4. validate accounting period;
5. calculate payment;
6. create payment;
7. allocate invoices;
8. mark invoices paid;
9. create journal;
10. audit transaction;
11. commit;
12. dispatch network unisolate job.

Network job terjadi setelah financial commit.

---

# 38. No Partial Payment

Backend tidak menerima:

```json
{
  "amount": "300000"
}
```

sebagai cara membayar sebagian outstanding Rp900.000.

Backend menentukan amount dari outstanding aktual.

---

# 39. Income Endpoints

```text
GET  /api/v1/incomes
GET  /api/v1/incomes/{income}

POST /api/v1/incomes/preview
POST /api/v1/incomes

POST /api/v1/incomes/{income}/reversal-request
```

---

# 40. Income Preview

Request:

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

Backend memvalidasi:

- account;
- period;
- amount;
- cash/bank;
- permission.

Response menyediakan journal preview.

---

# 41. Income Posting

Other Income:

- tidak memerlukan Owner approval;
- langsung diposting setelah confirmation;
- menghasilkan automatic journal;
- immutable setelah posted;
- correction melalui reversal.

---

# 42. Expense Endpoints

```text
GET  /api/v1/expenses
GET  /api/v1/expenses/{expense}

POST /api/v1/expenses
POST /api/v1/expenses/{expense}/submit

POST /api/v1/expenses/{expense}/approve
POST /api/v1/expenses/{expense}/reject

POST /api/v1/expenses/{expense}/reversal-request
```

---

# 43. Expense Workflow

Status:

`draft`

→ `pending_approval`

→ `approved`

atau

→ `rejected`

Financial posting hanya terjadi setelah Owner approve.

---

# 44. Expense Approval Security

Backend memverifikasi:

- actor adalah Owner/authorized approver;
- expense masih Pending;
- actor bukan self-approver jika policy melarang;
- accounting period masih Open;
- request belum pernah diproses.

Approval harus atomic.

---

# 45. Reversal Endpoints

General pattern:

```text
POST /api/v1/reversals/{type}/{id}/request
```

atau dedicated endpoint per resource.

Review:

```text
GET  /api/v1/approvals/reversals
POST /api/v1/reversal-requests/{request}/approve
POST /api/v1/reversal-requests/{request}/reject
```

Implementation style final harus konsisten.

---

# 46. Approval Endpoints

```text
GET /api/v1/approvals
GET /api/v1/approvals/{approval}

POST /api/v1/approvals/{approval}/approve
POST /api/v1/approvals/{approval}/reject
```

Jika menggunakan unified approval abstraction, response harus mencantumkan resource type.

---

# 47. Notification Endpoints

```text
GET  /api/v1/notifications
GET  /api/v1/notifications/unread-count

POST /api/v1/notifications/{notification}/read
POST /api/v1/notifications/read-all
```

---

# 48. Device Registration

Untuk push notification:

```text
POST   /api/v1/devices
PUT    /api/v1/devices/{device}
DELETE /api/v1/devices/{device}
```

Data minimal:

```json
{
  "device_id": "...",
  "platform": "android",
  "push_token": "...",
  "app_version": "1.0.0"
}
```

Push token dianggap sensitive application data.

---

# 49. File Upload

Digunakan untuk:

- expense receipt;
- other income attachment jika dibutuhkan.

Endpoint dapat menggunakan multipart form-data.

Rules:

- whitelist MIME;
- whitelist extension;
- maximum file size;
- generated server filename;
- jangan percaya original filename;
- scan/validation sesuai infrastructure.

Recommended allowed type awal:

- JPEG;
- PNG;
- PDF.

Limit final harus ditentukan dalam configuration.

---

# 50. Attachment Security

File tidak boleh diletakkan pada public URL permanen tanpa authorization.

Gunakan:

- authenticated download endpoint;
- signed temporary URL;
- private object storage.

User tetap harus memiliki permission untuk resource terkait.

---

# 51. Idempotency

Endpoint transaksi sensitif wajib mendukung idempotency.

Minimal:

- payment posting;
- income posting;
- expense submission;
- approval;
- reversal request;
- activation;
- network retry jika diperlukan.

Header:

`Idempotency-Key`

Contoh:

`Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000`

---

# 52. Idempotency Behavior

Untuk key yang sama dan payload sama:

backend mengembalikan result transaksi pertama.

Untuk key sama tetapi payload berbeda:

`409 Conflict`

Error:

`IDEMPOTENCY_CONFLICT`

Key disimpan bersama:

- user;
- endpoint;
- request fingerprint;
- response/reference;
- expiry.

---

# 53. Concurrency Protection

Financial posting harus menggunakan:

- DB transaction;
- row locking jika diperlukan;
- unique constraint;
- idempotency.

Contoh:

dua device mencoba membayar customer yang sama bersamaan.

Hanya satu transaksi boleh berhasil berdasarkan outstanding aktual.

Request lain menerima conflict/business validation.

---

# 54. Database Constraints

Gunakan constraint untuk critical invariants.

Contoh:

Recurring invoice:

`customer_id + billing_period`

harus unik sesuai aturan invoice regular.

Payment allocations tidak boleh menduplikasi invoice allocation yang tidak valid.

ONT active assignment harus konsisten.

---

# 55. API Security — HTTPS

Production API:

**HTTPS mandatory**

Tidak boleh menyediakan production HTTP endpoint untuk client.

Recommended:

- TLS modern;
- automatic certificate renewal;
- HSTS sesuai deployment policy.

---

# 56. API Security — Rate Limiting

Rate limit berbeda berdasarkan endpoint.

Contoh policy:

### Login

Sangat ketat.

Misalnya per:

- IP;
- email/account;
- device signal jika tersedia.

### Authenticated Read

Rate lebih tinggi.

### Financial Write

Rate terbatas tetapi tidak terlalu rendah sehingga mengganggu user valid.

Rate limit angka final configurable.

Response:

`429 Too Many Requests`

---

# 57. Login Brute Force Protection

Login wajib memiliki:

- rate limiting;
- failed attempt monitoring;
- audit logging;
- optional temporary account/IP throttling.

Jangan mengungkap apakah email tertentu terdaftar secara berlebihan.

Generic response:

> Email atau password tidak valid.

---

# 58. Password Security

Password:

- menggunakan Laravel hashing;
- tidak pernah disimpan plaintext;
- tidak pernah dikirim kembali melalui API;
- tidak masuk log.

Password reset flow jika dibuat harus melalui secure token mechanism.

---

# 59. Token Security

API token:

- hanya dikirim sekali saat login/creation;
- disimpan secara hashed/secure sesuai auth framework;
- dapat dicabut;
- dapat dibatasi umur;
- dapat di-revoke saat password/security change jika policy mengharuskan.

---

# 60. Device Session Security

Recommended:

Satu token terkait satu device/session.

Simpan metadata:

- user;
- device name;
- platform;
- last used;
- created;
- revoked.

Owner/admin dapat melihat session aktif melalui web pada future enhancement.

---

# 61. CORS

CORS tidak boleh menggunakan unrestricted configuration tanpa kebutuhan.

Untuk web:

whitelist Aionios.NET origin.

Mobile native tidak bergantung pada browser CORS, tetapi server configuration tetap harus aman.

---

# 62. CSRF

Jika mobile menggunakan Bearer token:

CSRF handling berbeda dari cookie session browser.

Jika web menggunakan cookie-based authentication:

CSRF protection Laravel tetap wajib.

Jangan mencampur kedua model tanpa design yang jelas.

---

# 63. Input Validation

Semua write endpoint menggunakan Laravel Form Request atau equivalent centralized validation.

Tidak boleh mengandalkan Flutter validation.

Backend memvalidasi:

- types;
- length;
- enum;
- date;
- decimal;
- foreign keys;
- business rules;
- permissions.

---

# 64. Mass Assignment Protection

Jangan menggunakan request payload langsung untuk update seluruh model.

Hindari pola:

```php
$model->update($request->all());
```

Gunakan validated/explicit fields.

Sensitive field seperti:

- role;
- approval status;
- journal status;
- account mapping;
- payment amount;

tidak boleh dapat dimodifikasi melalui mass assignment tidak terkontrol.

---

# 65. SQL Injection Protection

Gunakan:

- Eloquent;
- query builder parameter binding;
- validated sort/filter whitelist.

Jangan menggabungkan raw input ke raw SQL.

---

# 66. XSS Protection

API dapat menerima description/notes.

Server harus memperlakukan input sebagai data, bukan trusted HTML.

Jika rich text tidak diperlukan:

jangan izinkan arbitrary HTML.

Client juga wajib melakukan safe rendering.

---

# 67. Object-Level Authorization

Endpoint:

`GET /expenses/{id}`

tidak cukup hanya memeriksa bahwa user sudah login.

Backend harus memverifikasi user boleh melihat resource tersebut.

Ini berlaku untuk:

- customer;
- payment;
- expense;
- approval;
- attachment;
- network job;
- notification.

---

# 68. BOLA/IDOR Protection

Resource ID tidak boleh menjadi authorization mechanism.

Contoh:

Admin Keuangan mengganti URL:

`/expenses/100`

menjadi:

`/expenses/101`

Backend tetap melakukan policy check.

Tidak boleh menganggap ID yang sulit ditebak sebagai security control.

---

# 69. Sensitive Fields

API tidak boleh mengembalikan:

- password hash;
- MikroTik credential;
- DB credential;
- secret keys;
- internal encryption key;
- full private configuration;
- token user lain.

PPPoE credential hanya dikembalikan jika benar-benar diperlukan dan permission secara eksplisit mengizinkan.

Lebih aman tidak menampilkan password PPPoE kembali setelah dibuat jika workflow memungkinkan.

---

# 70. MikroTik Security Boundary

Flutter:

**tidak pernah menerima MikroTik API credential.**

Laravel menyimpan credential secara encrypted.

Laravel worker/service mengakses MikroTik melalui:

- IP whitelist;
- dedicated user;
- minimum permission;
- encrypted transport jika tersedia;
- timeout;
- retry policy.

---

# 71. Financial Authorization

Semua endpoint financial menggunakan kombinasi:

- authentication;
- permission;
- accounting period validation;
- immutable transaction rule;
- idempotency;
- DB transaction;
- audit trail.

Mobile biometric bukan backend authorization.

Backend tidak boleh mempercayai bahwa fingerprint sudah dilakukan hanya karena client mengatakan demikian.

---

# 72. Optional Step-Up Confirmation Token

Jika dibutuhkan security lebih kuat untuk sensitive mobile action, backend dapat menyediakan short-lived confirmation challenge/session.

Contoh:

1. mobile membuka payment preview;
2. user biometric/PIN di device;
3. mobile mengirim final posting menggunakan recent authenticated session + preview reference.

Biometric tetap diverifikasi OS, bukan Laravel.

Backend tidak menyimpan biometric information.

---

# 73. Audit Trail

API request sensitif wajib menghasilkan audit record.

Minimal:

- actor;
- timestamp;
- action;
- module;
- resource;
- resource ID;
- old/new values bila relevan;
- IP;
- user agent;
- source;
- reason;
- request/correlation ID.

Source:

`MOBILE`

atau:

`WEB`

---

# 74. Request Correlation ID

Setiap request sebaiknya mempunyai:

`X-Request-ID`

Jika client tidak mengirim, server membuat.

ID digunakan untuk:

- application log;
- error tracing;
- audit correlation;
- queue tracing.

Jangan gunakan sensitive data sebagai correlation ID.

---

# 75. Logging

Log harus mencatat:

- endpoint;
- request ID;
- actor ID;
- response status;
- duration;
- exception class.

Jangan log:

- password;
- Authorization header;
- API token;
- PIN;
- biometric data;
- MikroTik password;
- full financial attachment.

---

# 76. Exception Handling

Production API tidak boleh mengembalikan stack trace.

Response:

```json
{
  "success": false,
  "message": "An unexpected error occurred.",
  "error": {
    "code": "INTERNAL_ERROR",
    "request_id": "..."
  }
}
```

Stack trace hanya masuk protected application logs.

---

# 77. Environment Security

`.env` tidak masuk repository.

Gunakan environment/secret management untuk:

- database credential;
- APP_KEY;
- MikroTik credential;
- FCM credential;
- storage credential.

Production secret tidak digunakan di development.

---

# 78. Encryption

Sensitive configuration disimpan encrypted jika perlu.

Laravel encryption dapat digunakan untuk application-level secret seperti MikroTik credential.

Password tetap menggunakan hashing, bukan reversible encryption.

---

# 79. Database Security

MariaDB:

- tidak expose secara public;
- user application minimum required permission;
- strong password;
- network restriction;
- encrypted backup;
- backup retention;
- restore testing.

---

# 80. API Documentation menggunakan Scramble

Scramble digunakan untuk menghasilkan OpenAPI/API documentation dari Laravel API.

Dokumentasi digunakan oleh:

- Flutter developer;
- backend developer;
- QA;
- internal integrator.

Dokumentasi harus mengikuti route API v1.

---

# 81. Scramble Documentation Scope

Dokumentasikan:

- endpoint;
- HTTP method;
- route;
- authentication;
- permission requirement jika dapat ditampilkan;
- request parameters;
- request body;
- validation rules;
- response structure;
- example response;
- error response;
- pagination;
- enum;
- status.

---

# 82. Documentation URL

Recommended non-production/internal path:

`/docs/api`

atau:

`/docs/api/v1`

OpenAPI JSON:

`/docs/api.json`

Path final mengikuti Scramble configuration.

---

# 83. Documentation Security

Production API docs tidak harus tersedia untuk publik.

Recommended:

### Development

Accessible untuk developer.

### Staging

Authentication/internal network protection.

### Production

Salah satu:

- disabled;
- authenticated Owner/developer-only;
- protected by reverse proxy/IP allowlist.

Jangan mempublikasikan documentation internal tanpa kebutuhan.

---

# 84. Scramble Route Coverage

Scramble hanya perlu mendokumentasikan API routes.

Scope:

`api/v1/*`

Tidak perlu mendokumentasikan:

- Laravel health/debug internals;
- queue internal route;
- Horizon/internal admin jika tidak consumer-facing;
- authentication callback internal yang tidak dipakai mobile.

---

# 85. API Controller Documentation

Controller dan Form Request harus dibuat dengan struktur yang dapat dipahami generator OpenAPI.

Contoh konsep:

```php
final class PaymentController
{
    public function store(StorePaymentRequest $request)
    {
        // ...
    }
}
```

Gunakan typed request/response/resource bila membantu dokumentasi.

---

# 86. Request Documentation

Validasi sebaiknya terpusat pada Form Request.

Contoh:

```php
public function rules(): array
{
    return [
        'customer_id' => ['required', 'integer', 'exists:customers,id'],
        'payment_method' => ['required', 'in:manual,qris'],
        'cash_bank_account_id' => ['required', 'integer'],
    ];
}
```

Ini memudahkan code quality dan API documentation.

---

# 87. API Resources

Gunakan Laravel API Resource untuk menjaga response stabil.

Contoh:

- `CustomerResource`
- `PaymentResource`
- `ExpenseResource`
- `IncomeResource`
- `NetworkJobResource`
- `NotificationResource`

Jangan mengembalikan Eloquent model secara mentah jika dapat mengekspos field internal.

---

# 88. OpenAPI Examples

Dokumentasi critical endpoint harus memiliki contoh success dan failure.

Payment:

### Success

`201`

### Validation

`422`

### Unauthorized

`401`

### Forbidden

`403`

### Conflict

`409`

### Rate Limit

`429`

---

# 89. Scramble CI Validation

API documentation perlu masuk quality gate.

CI/CD sebaiknya menjalankan:

- tests;
- route validation;
- OpenAPI generation/check;
- lint/static analysis.

Breaking contract yang tidak disengaja harus dapat dideteksi melalui testing atau schema diff bila tooling tersedia.

---

# 90. Endpoint Test Requirement

Setiap endpoint critical memiliki feature test.

Minimal test:

- unauthenticated;
- unauthorized role;
- valid request;
- invalid validation;
- resource not found;
- business state conflict;
- idempotent retry;
- audit log;
- transaction rollback.

---

# 91. Payment Security Tests

Wajib menguji:

1. Admin Jaringan tidak dapat payment.
2. amount tidak dapat dimanipulasi.
3. MDR tidak dapat dimanipulasi.
4. duplicate idempotency key tidak membuat duplicate payment.
5. concurrent request tidak membuat double payment.
6. network failure tidak rollback payment.
7. closed period menolak posting.
8. posted payment tidak dapat diedit/delete.

---

# 92. Income Security Tests

Wajib:

- authorization;
- invalid account;
- invalid cash/bank;
- closed period;
- duplicate submission;
- immutable after posting;
- reversal workflow.

---

# 93. Expense Security Tests

Wajib:

- Admin Keuangan dapat submit;
- Admin Jaringan tidak dapat submit jika tidak punya permission;
- Admin Keuangan tidak dapat approve;
- Owner dapat approve;
- double approve gagal;
- rejected expense tidak mengurangi cash;
- approved expense membuat journal;
- closed period ditolak.

---

# 94. Network Security Tests

Wajib:

- unauthorized role tidak dapat isolate;
- queue command dibuat;
- failed MikroTik operation tercatat;
- retry dapat dilakukan sesuai permission;
- MikroTik secret tidak pernah muncul di API response.

---

# 95. File Security Tests

Wajib:

- invalid MIME ditolak;
- file terlalu besar ditolak;
- unauthorized attachment access ditolak;
- filename traversal tidak memungkinkan;
- public enumeration tidak dapat dilakukan.

---

# 96. Rate Limiting Tests

Wajib uji:

- brute force login;
- excessive endpoint calls;
- correct HTTP 429 response;
- valid user dapat kembali menggunakan endpoint setelah throttle window.

---

# 97. API Performance

Target awal:

- simple GET < 500 ms pada kondisi normal;
- customer search responsif;
- dashboard < 1 s backend processing target jika dataset normal;
- heavy report tidak masuk mobile API MVP;
- MikroTik command tidak memblokir HTTP request terlalu lama.

---

# 98. Queue

Queue digunakan untuk:

- MikroTik synchronization;
- push notification;
- potentially heavy async jobs.

Queue job harus:

- retry-safe;
- idempotent;
- logged;
- memiliki max attempt;
- mempunyai failed job handling.

---

# 99. Transaction Boundary

Jangan memasukkan third-party/network call panjang ke dalam financial DB transaction.

Contoh benar:

Payment DB transaction

→ COMMIT

→ dispatch network job

Contoh yang harus dihindari:

Begin DB

→ Payment

→ call MikroTik 30 detik

→ timeout

→ rollback payment.

---

# 100. Health Endpoint

Recommended:

`GET /api/v1/health`

Public output harus minimal.

Contoh:

```json
{
  "status": "ok"
}
```

Detailed dependency health harus protected/internal agar tidak membocorkan infrastructure.

---

# 101. API Deprecation

Jika v1 suatu saat diganti:

- v1 tidak langsung dimatikan;
- mobile mempunyai migration window;
- deprecation dikomunikasikan;
- v2 dibuat untuk breaking changes.

Optional response header:

`Deprecation`

atau mekanisme internal version policy.

---

# 102. Security Headers

API/reverse proxy perlu mempertimbangkan:

- HSTS;
- X-Content-Type-Options;
- appropriate cache controls;
- other relevant HTTP security headers.

JSON financial/auth responses harus menggunakan:

`Cache-Control: no-store`

bila mengandung sensitive information.

---

# 103. Cache Policy

Jangan cache secara publik:

- `/me`;
- customer financial detail;
- payment;
- income;
- expense;
- approval;
- notification.

Reference/master data tertentu dapat menggunakan controlled private caching bila diperlukan.

---

# 104. Mobile Security Interaction

PIN/Fingerprint/Face ID berada di Flutter.

Laravel hanya mengetahui:

- authenticated user;
- token/session;
- request;
- permission.

Laravel tidak menerima:

- fingerprint;
- face image;
- biometric template;
- raw PIN app-lock.

Ini menjaga boundary keamanan yang benar.

---

# 105. Endpoint Priority — P0

Implementasi awal:

### Auth

- login;
- logout;
- me.

### Customer

- list;
- detail;
- activate;
- terminate;
- reactivate.

### ONT

- list;
- detail;
- assign;
- return.

### Network

- status;
- jobs;
- retry;
- customer network status.

---

# 106. Endpoint Priority — P0 Financial

### Payment

- outstanding;
- preview;
- post;
- history.

### Income

- list;
- preview;
- post.

### Expense

- list;
- create;
- submit;
- approve;
- reject.

---

# 107. Endpoint Priority — P0 Notification

- list notification;
- unread count;
- mark read;
- device registration;
- device unregister.

---

# 108. Endpoint Priority — P1

- reversal request;
- reversal approval;
- ONT history detail;
- richer dashboard;
- push settings;
- device management;
- additional filters.

---

# 109. Definition of Done — API

Laravel API v1 dianggap siap ketika:

- `/api/v1` versioning aktif;
- authentication aktif;
- RBAC aktif;
- all P0 endpoints tersedia;
- Form Request validation digunakan;
- API Resources digunakan;
- error format konsisten;
- pagination aktif;
- idempotency untuk transaksi aktif;
- payment atomic;
- income atomic;
- expense approval atomic;
- audit trail aktif;
- rate limit aktif;
- HTTPS production aktif;
- secrets aman;
- MikroTik credential tidak terekspos;
- queue aktif;
- Scramble documentation tersedia;
- endpoint tests lulus;
- security tests lulus;
- staging UAT selesai.

---

# 110. Critical Acceptance Criteria

## Authentication

Login valid:

→ token diterbitkan.

Invalid password:

→ ditolak.

Repeated brute-force:

→ throttled.

---

## Authorization

Admin Jaringan:

`POST /payments`

→ `403`.

Owner:

expense approve

→ success.

---

## Payment

Outstanding Rp900.000.

Client mencoba mengirim Rp300.000.

Backend:

→ tidak mempercayai client amount.

→ menghitung kembali Rp900.000.

→ partial payment tidak terjadi.

---

## Idempotency

Dua POST payment dengan Idempotency-Key yang sama:

→ hanya satu payment terbentuk.

---

## Concurrent Payment

Dua device mencoba membayar customer yang sama.

→ satu request berhasil.

→ request lain tidak membuat duplicate payment.

---

## Network Failure

Payment posted.

MikroTik offline.

→ Payment tetap SUCCESS.

→ Network Job PENDING/FAILED.

---

## Expense

Admin Keuangan submit.

→ status Pending.

→ journal belum dibuat.

Owner approve.

→ journal dibuat.

→ saldo cash/bank berubah.

---

## API Documentation

Developer membuka Scramble docs.

→ seluruh endpoint `/api/v1` terlihat.

→ request schema terlihat.

→ response terlihat.

→ authentication requirement terlihat.

→ critical error response terdokumentasi.

---

## Security

Unauthenticated protected request:

→ `401`.

Unauthorized role:

→ `403`.

Invalid resource access:

→ `403/404` sesuai security policy.

Stack trace:

→ tidak terlihat di production.

Sensitive credential:

→ tidak muncul pada API response/log.

---

# 111. Recommended Security Checklist

- [ ] HTTPS enforced
- [ ] Authentication token secured
- [ ] Token revocation available
- [ ] Server-side RBAC
- [ ] Policy per sensitive resource
- [ ] Login rate limiting
- [ ] General API throttling
- [ ] Request validation
- [ ] Mass-assignment protection
- [ ] SQL injection protection
- [ ] BOLA/IDOR protection
- [ ] Financial transaction atomicity
- [ ] Idempotency
- [ ] Concurrency protection
- [ ] Immutable posted transaction
- [ ] Period lock validation
- [ ] Audit trail
- [ ] Sensitive logging redaction
- [ ] No stack trace production
- [ ] MikroTik credentials encrypted
- [ ] No MikroTik credentials in API
- [ ] Private attachment access
- [ ] MIME/file-size validation
- [ ] Environment secrets excluded from repository
- [ ] Database not publicly exposed
- [ ] Backup secured
- [ ] API docs protected in production
- [ ] Security headers
- [ ] Sensitive response no-store
- [ ] Feature/security tests in CI

---

# 112. Final Architecture

```text
Flutter
   │
   │ HTTPS + Bearer Token
   │ Idempotency-Key
   ▼
Laravel API v1
   │
   ├── Authentication
   ├── RBAC / Policies
   ├── Form Requests
   ├── API Resources
   ├── Domain Actions
   ├── Audit
   ├── Idempotency
   │
   ├──────────────► MariaDB
   │
   ├──────────────► Queue
   │                   │
   │                   ├── MikroTik
   │                   └── Push Notification
   │
   └──────────────► Private File Storage
```

Documentation:

```text
Laravel API v1
      │
      ▼
   Scramble
      │
      ▼
OpenAPI Documentation
```

Security boundary:

```text
Biometric / PIN
      │
   Flutter OS
      │
      ▼
Authenticated API Request
      │
      ▼
Laravel Authentication
      │
      ▼
RBAC / Policy
      │
      ▼
Business Validation
      │
      ▼
DB Transaction / Queue
```

---

# 113. Product Principle

Aionios.NET API v1 harus menjaga empat prinsip:

**Backend Authority**

Flutter tidak menentukan kebenaran transaksi.

**Financial Integrity**

Payment, income, expense, approval, dan reversal tidak dapat dimanipulasi dari client.

**Secure by Default**

Setiap endpoint protected, validated, authorized, rate-limited sesuai risiko, dan audited.

**Documented Contract**

Scramble/OpenAPI menjadi dokumentasi contract resmi antara Laravel dan Flutter.

Dengan struktur tersebut, pengembangan Flutter dan Laravel dapat berjalan paralel karena tim memiliki satu API contract, sementara business rules tetap tersentralisasi dan aman di backend.