# Product Requirements Document (PRD) — Aionios.NET

**Versi:** 1.0  
**Status:** Initial Product Specification  
**Platform:** Web Application  
**Deployment:** VPS Singapore  
**Backend:** Laravel  
**Frontend:** Svelte  
**Database:** MariaDB  
**UI System:** shadcn/ui-inspired professional interface  
**Integrasi Jaringan:** MikroTik RouterOS 7.24  
**Target:** Sistem internal ISP, single-company / non-SaaS

---

## 1. Ringkasan Produk

**Aionios.NET** adalah web application internal untuk mengintegrasikan operasional pelanggan ISP, billing, pembayaran, MikroTik PPPoE, promo, ONT, keuangan, akuntansi, dan laporan manajemen dalam satu sistem.

Aionios.NET menggantikan proses manual dan data yang saat ini belum terintegrasi.

Alur utama sistem:

**Pelanggan → Paket → PPPoE → Promo → Tagihan → Pembayaran → Isolir/Unisolir → Akuntansi → Laporan Keuangan**

Aplikasi digunakan hanya untuk operasional perusahaan sendiri dan tidak dirancang sebagai SaaS/multi-tenant pada versi awal.

---

# 2. Tujuan Produk

Aionios.NET bertujuan untuk:

1. Mengintegrasikan data pelanggan, jaringan, billing, dan keuangan.
2. Mengurangi proses administrasi manual.
3. Membuat billing pelanggan secara otomatis.
4. Mengotomatisasi isolir pelanggan yang menunggak.
5. Mengaktifkan kembali pelanggan setelah seluruh tagihan dilunasi.
6. Mengelola PPPoE MikroTik langsung dari aplikasi.
7. Mengelola paket dan promo pelanggan.
8. Mengelola pembayaran manual dan QRIS manual.
9. Menghasilkan jurnal akuntansi secara otomatis.
10. Mengelola COA, buku besar, modal, kas/bank, dan pengeluaran.
11. Menyediakan laporan keuangan utama.
12. Menjaga histori transaksi dan aktivitas melalui audit trail.
13. Menyediakan informasi bisnis berbeda sesuai role.
14. Melacak ONT milik perusahaan beserta histori penggunaannya.

---

# 3. Masalah yang Diselesaikan

Saat ini proses operasional masih manual dan tidak terintegrasi.

Permasalahan utama:

- data pelanggan terpisah;
- pengelolaan PPPoE dilakukan terpisah di MikroTik;
- billing dilakukan manual;
- pembayaran tidak langsung terhubung dengan status layanan;
- isolir pelanggan dilakukan manual;
- promo sulit ditelusuri;
- pencatatan pemasukan dan pengeluaran belum terintegrasi;
- laporan keuangan membutuhkan proses manual;
- histori ONT sulit ditelusuri;
- koreksi transaksi berisiko menghilangkan histori;
- owner tidak memiliki satu dashboard untuk melihat kondisi bisnis secara keseluruhan.

Aionios.NET menjadi **single source of truth** untuk aktivitas tersebut.

---

# 4. User & Role

## 4.1 Owner / Super Admin

Owner memiliki akses tertinggi.

Hak akses utama:

- seluruh dashboard perusahaan;
- seluruh pelanggan;
- seluruh data keuangan;
- laporan keuangan;
- user management;
- konfigurasi aplikasi;
- konfigurasi MikroTik;
- konfigurasi COA;
- approval pengeluaran;
- approval reversal;
- accounting period closing;
- membuka kembali accounting period;
- audit log;
- konfigurasi pembayaran;
- konfigurasi MDR;
- seluruh fungsi Admin Keuangan;
- seluruh fungsi Admin Jaringan.

---

## 4.2 Admin Keuangan

Fokus pada billing dan accounting.

Hak akses antara lain:

- melihat pelanggan;
- melihat invoice;
- konfirmasi pembayaran;
- pembayaran manual;
- pembayaran QRIS;
- kas/bank;
- pemasukan lain;
- modal;
- pengajuan pengeluaran;
- COA sesuai permission;
- jurnal;
- buku besar;
- laporan keuangan;
- mengajukan reversal;
- melihat status approval.

Admin Keuangan tidak dapat menyetujui pengeluarannya sendiri.

---

## 4.3 Admin Jaringan

Fokus pada pelanggan dan jaringan.

Hak akses:

- pelanggan;
- aktivasi pelanggan;
- terminasi pelanggan;
- reaktivasi;
- paket internet;
- harga paket;
- PPP Profile;
- PPP Secret;
- promo;
- assignment promo;
- ONT;
- status PPPoE;
- isolir/unisolir;
- MikroTik synchronization;
- integration log terkait jaringan.

---

# 5. Authentication & Authorization

Login menggunakan:

- email;
- password.

Tidak ada 2FA pada versi awal.

Sistem wajib menggunakan Role-Based Access Control.

Semua endpoint backend tetap harus melakukan authorization server-side. Menyembunyikan menu frontend tidak dianggap sebagai mekanisme keamanan.

Session harus memiliki timeout yang configurable.

Password wajib disimpan menggunakan hashing standar Laravel.

---

# 6. Dashboard

Dashboard berbeda berdasarkan role.

## 6.1 Owner Dashboard

Menampilkan minimal:

### KPI Keuangan

- pendapatan bulan berjalan;
- pendapatan internet;
- pendapatan lain;
- pengeluaran;
- laba/rugi;
- total kas/bank;
- piutang pelanggan;
- MDR QRIS;
- modal.

### KPI Pelanggan

- total pelanggan;
- pelanggan aktif;
- pelanggan isolir;
- pelanggan berhenti;
- pelanggan baru;
- pelanggan reaktivasi.

### Billing

- invoice bulan berjalan;
- invoice lunas;
- invoice outstanding;
- total tunggakan.

### Approval

- pengeluaran menunggu approval;
- reversal menunggu approval.

### Network

- status MikroTik;
- jumlah PPPoE aktif;
- pelanggan isolir;
- pending synchronization;
- integration error.

---

## 6.2 Dashboard Admin Keuangan

Fokus pada:

- tagihan;
- jatuh tempo;
- outstanding;
- pembayaran hari ini;
- pembayaran QRIS;
- pembayaran manual;
- saldo kas/bank;
- pemasukan;
- pengeluaran;
- pengeluaran pending;
- jurnal;
- closing period;
- reversal pending.

---

## 6.3 Dashboard Admin Jaringan

Fokus pada:

- pelanggan aktif;
- pelanggan isolir;
- pelanggan berhenti;
- PPPoE online;
- PPPoE offline;
- paket;
- promo aktif;
- promo akan berakhir;
- ONT terpasang;
- ONT tersedia;
- MikroTik connectivity;
- pending sync;
- failed sync.

---

# 7. Customer Management

## 7.1 Data Pelanggan

Minimal menyimpan:

- Customer ID;
- nama pelanggan;
- nomor HP;
- alamat pemasangan;
- tanggal pemasangan;
- tanggal aktivasi;
- paket aktif;
- harga paket;
- PPPoE username;
- PPPoE configuration;
- ONT;
- promo aktif;
- status;
- catatan.

NIK tidak diperlukan.

Customer ID harus unik dan tidak berubah selama lifecycle pelanggan.

---

# 8. Status Pelanggan

Minimal:

- Active;
- Isolated;
- Terminated.

Pelanggan yang berhenti **tidak dihapus**.

Saat berhenti:

- status menjadi Terminated;
- billing berikutnya dihentikan;
- PPPoE dinonaktifkan sesuai prosedur terminasi;
- histori tetap tersimpan;
- invoice lama tetap tersimpan;
- piutang tetap tersimpan;
- payment history tetap tersedia;
- ONT history tetap tersedia.

---

# 9. Reactivation

Pelanggan lama dapat direaktivasi menggunakan Customer ID yang sama.

Syarat:

**Outstanding Balance = Rp0**

Jika masih terdapat tunggakan, tombol reaktivasi harus diblokir.

Reaktivasi dapat menentukan:

- tanggal aktivasi baru;
- paket;
- PPP Profile;
- PPPoE credential;
- ONT.

Tagihan pertama setelah reaktivasi menggunakan aturan prorata.

---

# 10. Internet Package Management

Satu pelanggan hanya dapat memiliki **satu paket aktif**.

Data paket:

- kode;
- nama paket;
- download speed;
- upload speed;
- harga bulanan;
- PPP Profile MikroTik;
- status aktif/nonaktif;
- deskripsi.

Perubahan harga paket tidak boleh mengubah invoice yang sudah terbit.

Harga baru berlaku pada billing berikutnya.

Invoice wajib menyimpan snapshot paket dan harga.

---

# 11. Promo Management

Promo diberikan **manual oleh Admin Jaringan**.

Periode promo bersifat dinamis berdasarkan bulan.

Contoh:

- 1 bulan;
- 2 bulan;
- 3 bulan;
- 6 bulan;
- dst.

---

## 11.1 Promo Naik Speed Harga Tetap

Contoh:

Normal:

20 Mbps — Rp250.000

Promo:

30 Mbps — Rp250.000

Sistem mengubah PPP Profile sesuai profile promo tanpa mengubah harga billing.

Ketika promo berakhir, PPP Profile dikembalikan otomatis ke paket normal.

---

## 11.2 Promo Speed Tetap Harga Turun

Contoh:

Normal:

20 Mbps — Rp250.000

Promo:

20 Mbps — Rp200.000 selama 3 bulan.

Speed tidak berubah.

Invoice menggunakan harga promo selama periode promo.

---

## 11.3 Special Discount

Mendukung:

### Fixed Amount

Contoh:

Rp50.000/bulan.

### Percentage

Contoh:

10%/bulan.

---

# 12. Promo Billing Rules

Promo harga dievaluasi ketika invoice diterbitkan.

Invoice yang sudah terbit tidak boleh berubah akibat promo baru.

Jika promo diberikan setelah invoice bulan tersebut terbit, promo harga berlaku pada invoice berikutnya.

**Tagihan pertama pelanggan baru selalu menggunakan harga normal**, bukan harga promo.

Promo speed dapat mulai berlaku sesuai tanggal aktivasi promo.

Ketika promo berakhir, sistem otomatis mengembalikan harga/profile ke kondisi normal sesuai tipe promo.

---

# 13. Billing Engine

Billing reguler dibuat otomatis:

**Tanggal 1 setiap bulan.**

Due date:

**Tanggal 22.**

Invoice yang belum dibayar tetap outstanding.

---

# 14. Recurring Outstanding Invoice

Walaupun pelanggan memiliki tunggakan, invoice bulan berikutnya **tetap diterbitkan**.

Contoh:

Januari Rp300.000 — Outstanding  
Februari Rp300.000 — Outstanding  
Maret Rp300.000 — Outstanding

Total outstanding:

Rp900.000.

---

# 15. Tidak Ada Pembayaran Parsial

Aionios.NET tidak mendukung cicilan/partial payment untuk billing internet.

Pelanggan wajib membayar **seluruh outstanding invoice**.

Jika outstanding Rp900.000, pembayaran harus melunasi Rp900.000.

---

# 16. First Invoice / Prorata

Pelanggan baru dihitung prorata berdasarkan tanggal pemasangan/aktivasi.

Formula:

**Harga Paket ÷ Jumlah Hari Kalender Bulan × Jumlah Hari Aktif**

Tanggal pemasangan termasuk hari aktif.

Contoh:

Harga = Rp300.000  
Bulan = 30 hari  
Aktif = tanggal 16

Hari aktif = 15.

Tagihan:

Rp300.000 ÷ 30 × 15 = **Rp150.000**

Sistem harus mendefinisikan aturan pembulatan nominal secara konsisten.

Tagihan prorata pertama menggunakan **harga normal**, bukan harga promo.

---

# 17. Auto Isolation

Jika invoice belum dilunasi sampai tanggal 22:

**Tanggal 23 pukul 01:00**

sistem menjalankan isolir otomatis.

Timezone scheduler wajib menggunakan timezone bisnis perusahaan di Indonesia dan tidak bergantung pada timezone VPS Singapore.

---

# 18. Isolation Mechanism

PPP Secret **tidak di-disable** untuk isolir billing.

Pelanggan dipindahkan ke:

**PPP Profile `ISOLIR`**

Sistem harus menyimpan profile yang seharusnya digunakan pelanggan agar dapat dikembalikan setelah pembayaran.

Prioritas restore:

1. profile promo aktif;
2. jika tidak ada promo, profile paket normal.

---

# 19. Payment & Auto Unisolate

Setelah seluruh outstanding dibayar:

1. payment diposting;
2. semua invoice terkait menjadi paid;
3. sistem mengecek status pelanggan;
4. jika isolated, sistem melakukan un-isolate;
5. profile dikembalikan;
6. audit log dibuat.

Jika MikroTik gagal diakses, pembayaran **tetap valid**.

Network operation masuk:

**Pending Sync**

dan dapat di-retry.

---

# 20. Payment Confirmation

Admin Keuangan membuka daftar tagihan/pelanggan.

Tersedia aksi:

**Konfirmasi Pembayaran**

Satu pembayaran dapat melunasi beberapa invoice sekaligus.

Sebelum posting wajib terdapat **Payment Preview**.

Preview minimal menampilkan:

- pelanggan;
- invoice yang dilunasi;
- periode;
- total outstanding;
- metode pembayaran;
- kas/bank tujuan;
- MDR;
- biaya MDR;
- penerimaan bersih;
- jurnal yang akan terbentuk.

Setelah admin menyetujui preview, transaksi diposting.

---

# 21. Payment Methods

Versi awal mendukung:

### Manual

Tidak ada MDR.

### QRIS Manual

Tidak ada payment gateway/API QRIS otomatis.

Admin memilih QRIS ketika mengonfirmasi pembayaran.

---

# 22. QRIS MDR

MDR harus **configurable/dinamis**.

Sistem memiliki:

**Default MDR**

Konfigurasi minimal:

- persentase MDR;
- effective date;
- status aktif;
- rekening tujuan/default kas-bank;
- COA Beban MDR.

Setiap payment menyimpan snapshot MDR yang digunakan.

Perubahan default MDR tidak mengubah transaksi historis.

Contoh:

Invoice = Rp300.000  
MDR = 0,7%

MDR:

Rp2.100

Net settlement:

Rp297.900.

Pelanggan tetap dianggap membayar:

**Rp300.000**

MDR bukan diskon pelanggan.

---

# 23. Payment Reversal

Payment yang sudah posted:

**tidak dapat diedit atau dihapus langsung.**

Admin Keuangan dapat mengajukan:

**Payment Reversal**

Wajib mengisi alasan.

Status:

`Pending Owner Approval`

Owner dapat:

- approve;
- reject.

Jika approve:

- sistem membuat reversal;
- invoice kembali outstanding;
- jurnal pembalik dibuat;
- customer outstanding dihitung ulang;
- status isolation dievaluasi ulang.

Semua tindakan masuk audit log.

---

# 24. MikroTik Integration

Target:

**MikroTik RouterOS 7.24**

Arsitektur awal hanya menggunakan **1 router**.

MikroTik bertindak sebagai:

**PPPoE Server**

ONT pelanggan bertindak sebagai:

**PPPoE Client**

---

# 25. PPP Secret Management

PPP Secret dikelola langsung melalui Aionios.NET.

Admin Jaringan dapat:

- membuat PPP Secret;
- menentukan username;
- mengatur credential;
- memilih PPP Profile;
- mengubah profile;
- melihat status;
- isolir;
- un-isolir;
- terminasi;
- reaktivasi.

Admin tidak perlu menggunakan Winbox untuk pekerjaan rutin tersebut.

---

# 26. MikroTik Connectivity

Aionios.NET berada pada VPS Singapore.

MikroTik berada di Indonesia dan memiliki:

**Public Static IP**

Aplikasi mengakses API MikroTik melalui public IP.

Karena API terekspos melalui internet, requirement keamanan minimum:

- source IP whitelist;
- hanya IP publik VPS Aionios.NET yang diperbolehkan;
- firewall MikroTik menolak sumber lain;
- gunakan transport terenkripsi/API SSL bila tersedia untuk metode integrasi yang dipilih;
- user MikroTik khusus aplikasi;
- minimum required permissions;
- credential terenkripsi;
- connection timeout;
- limited retry;
- integration log;
- health monitoring.

Port API tidak boleh dibuka bebas untuk seluruh internet.

---

# 27. Network Command Queue

Command MikroTik tidak boleh membuat transaksi finansial gagal.

Contoh:

Pembayaran berhasil → MikroTik unreachable.

Hasil:

Payment = **SUCCESS**

Network sync = **PENDING**

Sistem membuat queued action:

`UNISOLATE CUSTOMER`

Admin Jaringan mendapatkan internal notification.

Queue melakukan retry sesuai kebijakan sistem.

Status minimal:

- Pending;
- Processing;
- Success;
- Failed;
- Manual Retry Required.

---

# 28. ONT Inventory & Traceability

Versi awal tidak memiliki modul fixed asset lengkap.

ONT tetap dilacak sebagai perangkat milik perusahaan.

Data ONT:

- ONT ID;
- brand;
- model;
- serial number;
- MAC address bila digunakan;
- status;
- kondisi;
- pelanggan;
- tanggal pemasangan;
- tanggal penarikan;
- catatan.

Status contoh:

- Available;
- Installed;
- Returned;
- Damaged;
- Lost.

---

# 29. ONT History

Histori ONT tidak boleh hilang.

Sistem harus dapat menjawab:

- ONT sekarang berada di mana;
- pernah dipasang ke pelanggan siapa;
- kapan dipasang;
- kapan ditarik;
- siapa admin yang melakukan;
- kondisi saat pemasangan;
- kondisi saat penarikan.

ONT dapat dipindahkan ke pelanggan lain tanpa menghapus histori sebelumnya.

---

# 30. Accounting Module

Aionios.NET menyediakan accounting terintegrasi.

Scope awal:

- Chart of Accounts;
- Opening Balance;
- Automatic Journal;
- Manual Journal;
- General Ledger;
- Trial Balance;
- Income Statement;
- Balance Sheet;
- Cash Flow;
- Statement of Changes in Equity;
- Revenue Report;
- Receivable Report;
- cash/bank;
- other income;
- operational expenses;
- capital/equity.

---

# 31. PSAK Position

Sistem dirancang agar struktur accounting dapat mendukung penyusunan laporan berdasarkan kebijakan akuntansi perusahaan dan prinsip PSAK yang relevan.

Namun, aplikasi **tidak boleh mengklaim otomatis “PSAK compliant” hanya karena menyediakan format laporan**.

Khusus kebijakan bisnis yang dipilih saat ini, pendapatan internet diinginkan untuk diakui ketika pelanggan membayar. Kebijakan tersebut harus dibuat eksplisit/configurable karena pengakuan pendapatan berbasis pembayaran tidak selalu identik dengan prinsip pengakuan pendapatan berdasarkan pemenuhan kewajiban pelaksanaan dalam PSAK 72.

Final accounting policy harus dapat disesuaikan berdasarkan arahan akuntan perusahaan.

---

# 32. Chart of Accounts

Sistem menyediakan **Default ISP Chart of Accounts Template**.

Kelompok utama:

### Assets
- Kas
- Bank
- Piutang Usaha
- akun aset lainnya

### Liabilities
- Hutang Usaha
- kewajiban lainnya

### Equity
- Modal Pemilik
- Prive
- Saldo Laba

### Revenue
- Pendapatan Internet
- Pendapatan Lain

### Expenses
- Beban Bandwidth/Upstream
- Beban Listrik
- Beban Gaji
- Beban Maintenance Jaringan
- Beban Transportasi
- Beban Sewa
- Beban MDR QRIS
- Beban Operasional Lain

Admin berwenang dapat menambah atau menyesuaikan COA.

Automatic journal tidak boleh bergantung pada ID akun yang di-hardcode.

Account mapping harus configurable.

---

# 33. Opening Balance

Karena Aionios.NET menggantikan sistem manual, tersedia:

**Opening Balance**

Dapat digunakan untuk:

- kas;
- bank;
- piutang;
- hutang;
- modal;
- akun lainnya.

Sebelum posting:

**Total Debit = Total Credit**

Jika tidak balance, posting harus ditolak.

Piutang/tagihan lama pelanggan tidak perlu dimigrasikan secara individual.

---

# 34. Multi Cash & Bank

Aionios.NET mendukung banyak akun kas/bank.

Contoh:

- Kas Tunai;
- Bank BCA;
- Bank BRI;
- QRIS Settlement;
- rekening lainnya.

Setiap transaksi wajib memiliki account destination/source yang sesuai.

Dashboard menampilkan:

- saldo per akun;
- total saldo kas/bank.

---

# 35. Other Income

Admin Keuangan dapat mencatat pemasukan selain internet.

Data:

- tanggal;
- COA pendapatan;
- deskripsi;
- nominal;
- kas/bank tujuan;
- reference;
- attachment bila diperlukan.

Posting menghasilkan jurnal otomatis.

Tidak memerlukan approval Owner.

---

# 36. Capital / Equity

Sistem mendukung:

- setoran modal;
- penambahan modal;
- pengurangan modal/prive;
- koreksi melalui mekanisme jurnal/reversal yang diizinkan.

Contoh setoran modal:

Debit Kas/Bank  
Kredit Modal Pemilik

Transaksi modal normal tidak memerlukan approval Owner.

Koreksi posted transaction tetap menggunakan reversal approval.

---

# 37. Operational Expenses

Admin Keuangan membuat pengajuan pengeluaran.

Data:

- tanggal;
- expense COA;
- deskripsi;
- nominal;
- sumber kas/bank;
- attachment/bukti;
- catatan.

Workflow:

**Draft → Pending Approval → Approved / Rejected**

Hanya Owner dapat approve/reject.

Jurnal dan pengurangan kas/bank baru terjadi setelah approval.

---

# 38. Automatic Journal

Sistem menghasilkan jurnal berdasarkan transaction rules.

Contoh kategori:

- payment;
- QRIS MDR;
- other income;
- operational expense;
- capital;
- opening balance;
- reversal.

Setiap automatic journal wajib memiliki referensi ke source transaction.

---

# 39. Manual Journal

Admin Keuangan dapat membuat jurnal manual untuk:

- adjustment;
- correction sesuai policy;
- accrual;
- transaksi non-rutin.

Jurnal wajib:

**Debit = Credit**

Jurnal yang sudah posted tidak dapat diedit langsung.

---

# 40. Immutable Posted Transactions

Prinsip utama:

**Posted financial transaction is immutable.**

Tidak diperbolehkan:

- hard delete;
- edit nominal langsung;
- mengganti account secara diam-diam.

Koreksi dilakukan melalui:

**Reversal**

dengan reason wajib dan approval Owner.

---

# 41. Reversal Workflow

Berlaku untuk:

- payment;
- other income;
- capital;
- manual journal;
- transaksi finansial posted lainnya.

Flow:

Admin → Request Reversal → Reason → Owner Review → Approve/Reject.

Jika approve:

- reversal entry dibuat;
- original transaction tetap tersimpan;
- relationship original ↔ reversal disimpan.

---

# 42. Accounting Period

Accounting period berbasis bulan.

Contoh:

August 2026.

Status:

- Open;
- Closed.

---

# 43. Period Closing / Lock

Hanya Owner dapat melakukan:

**Close Period**

Setelah closed, tidak boleh ada:

- backdated transaction;
- journal baru;
- reversal;
- perubahan financial posting pada periode tersebut.

Owner dapat melakukan:

**Re-open Period**

Reason wajib.

Semua close/re-open masuk audit log.

---

# 44. General Ledger

Buku Besar mendukung filter:

- periode;
- account;
- transaction type;
- reference.

Menampilkan:

- opening balance;
- debit;
- credit;
- running balance;
- ending balance.

---

# 45. Trial Balance

Menampilkan:

- account code;
- account name;
- debit;
- credit;
- ending balance.

Sistem melakukan validation agar ledger tetap balanced.

---

# 46. Income Statement

Menampilkan minimal:

- Pendapatan Internet;
- Pendapatan Lain;
- total pendapatan;
- beban operasional;
- MDR;
- beban lainnya;
- laba/rugi periode.

Filter:

- bulan;
- rentang periode.

---

# 47. Balance Sheet

Menampilkan:

### Assets
- Cash;
- Bank;
- Receivables;
- other applicable assets.

### Liabilities
- Payables;
- other liabilities.

### Equity
- Capital;
- retained earnings/current result;
- relevant equity accounts.

Validasi:

**Assets = Liabilities + Equity**

---

# 48. Cash Flow

Laporan Arus Kas masuk scope laporan utama.

Klasifikasi dan metode penyajian harus mengikuti mapping COA/kebijakan akuntansi yang dikonfigurasi.

---

# 49. Statement of Changes in Equity

Menampilkan antara lain:

- opening equity;
- additional capital;
- withdrawals/prive;
- profit/loss;
- ending equity.

---

# 50. Receivable Report

Menampilkan:

- pelanggan;
- invoice;
- periode;
- due date;
- outstanding;
- umur piutang;
- customer status.

Dapat difilter berdasarkan:

- periode;
- pelanggan;
- status;
- aging.

---

# 51. Revenue Report

Menampilkan:

- internet revenue;
- other revenue;
- period;
- customer/package bila relevan;
- payment method;
- gross amount;
- MDR;
- net settlement.

---

# 52. PDF Reporting

Versi awal mendukung:

**Preview Web + Export PDF**

Tidak diperlukan Excel/CSV export.

Laporan PDF utama:

- Income Statement;
- Balance Sheet;
- Cash Flow;
- Changes in Equity;
- General Ledger;
- Trial Balance;
- General Journal;
- Revenue;
- Receivables;
- Billing;
- Payments;
- QRIS/MDR;
- customer report bila diperlukan.

PDF harus memiliki:

- nama Aionios.NET/perusahaan;
- nama laporan;
- periode;
- tanggal generate;
- generated by;
- nomor halaman.

---

# 53. Internal Notifications

Versi awal tidak menggunakan:

- WhatsApp;
- SMS;
- email notification.

Notifikasi hanya internal.

Event penting:

- invoice overdue;
- auto-isolation;
- un-isolation;
- MikroTik failure;
- pending sync;
- promo akan berakhir;
- promo berakhir;
- expense approval;
- reversal approval;
- approval result;
- accounting period activity.

---

# 54. Audit Trail

Audit trail wajib untuk aktivitas sensitif.

Minimal menyimpan:

- actor/user;
- timestamp;
- action;
- module;
- entity;
- entity ID;
- old value;
- new value;
- IP address;
- reason jika tersedia.

Event penting:

- login;
- customer change;
- package change;
- price change;
- promo;
- PPP Secret;
- isolation;
- un-isolation;
- payment;
- reversal;
- expense approval;
- journal;
- COA;
- closing;
- reopening;
- MikroTik configuration.

Audit log tidak dapat dihapus oleh user biasa.

---

# 55. Scheduler & Background Jobs

Laravel Scheduler/Queue menangani:

### Monthly
Tanggal 1:
- generate recurring invoice.

### Daily / Scheduled
- due-date evaluation;
- promo expiration;
- MikroTik health check;
- pending sync retry;
- notification processing.

### Tanggal 23 pukul 01:00
- evaluasi pelanggan overdue;
- pindahkan profile ke `ISOLIR`;
- simpan network job result.

Scheduler wajib menggunakan timezone bisnis Indonesia.

Job harus **idempotent**, sehingga retry tidak membuat invoice ganda, payment ganda, atau network action yang tidak konsisten.

---

# 56. Recommended Technical Architecture

## Frontend

**Svelte**

Tanggung jawab:

- SPA/web UI;
- dashboard;
- forms;
- tables;
- preview;
- charts;
- responsive interaction.

## Backend

**Laravel**

Tanggung jawab:

- authentication;
- RBAC;
- REST API;
- business rules;
- billing engine;
- accounting engine;
- approval workflow;
- scheduler;
- queue;
- MikroTik integration;
- PDF generation;
- audit trail.

## Database

**MariaDB**

Gunakan transactional storage engine dan database constraints untuk menjaga integritas data finansial.

---

# 57. Suggested Backend Domain Modules

Laravel disarankan dipisahkan secara domain:

- Authentication
- Users & Roles
- Customers
- Packages
- Promotions
- Billing
- Payments
- Network
- MikroTik
- ONT Inventory
- Accounting
- Cash & Bank
- Expenses
- Capital
- Approvals
- Reports
- Notifications
- Audit
- Settings

Hindari menempatkan seluruh business logic dalam Controller.

Gunakan Service/Action/Domain layer sesuai kebutuhan.

---

# 58. Suggested Core Database Entities

Entitas utama minimal:

- users
- roles
- permissions
- customers
- customer_status_histories
- packages
- customer_packages
- promotions
- customer_promotions
- onts
- ont_histories
- mikrotik_routers
- ppp_accounts
- network_jobs
- network_logs
- invoices
- invoice_items
- payments
- payment_allocations
- payment_methods
- payment_reversals
- cash_bank_accounts
- chart_of_accounts
- account_mappings
- journal_entries
- journal_lines
- accounting_periods
- opening_balances
- other_incomes
- expenses
- expense_approvals
- capital_transactions
- reversal_requests
- notifications
- audit_logs
- application_settings.

Walaupun versi awal hanya menggunakan satu MikroTik, tabel router tetap disarankan agar arsitektur tidak terkunci secara permanen ke satu perangkat.

---

# 59. Monetary Data Requirements

Semua nominal uang tidak boleh menggunakan floating point.

Gunakan tipe database seperti:

`DECIMAL`

dengan precision yang sesuai.

Persentase MDR dan diskon juga menggunakan decimal.

Setiap kalkulasi harus memiliki aturan rounding yang konsisten.

---

# 60. Invoice Snapshot

Invoice harus menyimpan snapshot informasi finansial yang relevan.

Contoh:

- package name;
- normal price;
- promo;
- discount type;
- discount value;
- final amount;
- billing period;
- calculation details.

Perubahan master data tidak boleh mengubah invoice historis.

---

# 61. Transaction Atomicity

Operasi finansial harus menggunakan database transaction.

Contoh payment posting:

1. validate outstanding;
2. create payment;
3. allocate invoice;
4. mark invoices paid;
5. create journal;
6. commit.

Network un-isolation dilakukan sebagai proses terpisah setelah transaksi finansial berhasil.

Dengan demikian MikroTik timeout tidak melakukan rollback terhadap pembayaran yang valid.

---

# 62. UI/UX Direction

Tema:

**Professional / Modern / Clean / Financial + Network Operations**

Style mengacu pada pola komponen **shadcn/ui**.

Karakter visual:

- clean;
- compact tetapi readable;
- professional;
- minim decorative element;
- data-centric;
- responsive;
- konsisten.

---

# 63. UI Components

Gunakan pola komponen seperti:

- Sidebar
- Breadcrumb
- Card
- Data Table
- Badge
- Dialog
- Alert Dialog
- Sheet
- Tabs
- Dropdown
- Command
- Tooltip
- Toast
- Calendar
- Date Picker
- Select
- Combobox
- Skeleton
- Pagination
- Chart
- Confirmation Dialog.

---

# 64. Color System

Gunakan warna profesional dengan dominasi neutral.

Semantic colors digunakan secara konsisten:

- Success → pembayaran berhasil / active;
- Warning → mendekati jatuh tempo / pending;
- Destructive → overdue / isolated / failed;
- Info → informational;
- Neutral → inactive/archived.

Warna tidak boleh menjadi satu-satunya indikator status. Selalu gunakan label/icon/text.

---

# 65. Table UX

Karena aplikasi data-heavy, tabel merupakan komponen utama.

Fitur tabel:

- search;
- filter;
- sort;
- pagination;
- status badge;
- column visibility bila diperlukan;
- row action;
- detail view.

Contoh customer table:

| Customer ID | Nama | Paket | Tagihan | Status | PPPoE | ONT | Action |
|---|---|---|---|---|---|---|---|

---

# 66. Payment UX

Payment confirmation harus menggunakan multi-step confirmation.

### Step 1
Pilih pelanggan.

### Step 2
Sistem mengambil semua outstanding invoice.

### Step 3
Pilih metode:
- Manual
- QRIS.

### Step 4
Pilih Kas/Bank.

### Step 5
Preview.

Untuk QRIS tampilkan:

**Total Invoice**  
**MDR %**  
**MDR Amount**  
**Net Settlement**

### Step 6
Konfirmasi final.

### Step 7
Tampilkan:
- Payment ID;
- invoice lunas;
- journal reference;
- network activation status.

---

# 67. Error Handling

Error harus actionable.

Contoh MikroTik:

> Pembayaran berhasil dicatat, tetapi pelanggan belum berhasil diaktifkan karena MikroTik tidak dapat dihubungi. Perintah aktivasi telah dimasukkan ke antrean sinkronisasi.

Bukan:

> Internal Server Error.

Financial transaction dan network synchronization harus memiliki state yang terpisah.

---

# 68. Data Retention

Tidak boleh melakukan hard delete terhadap:

- customer dengan histori;
- invoice;
- payment;
- journal;
- posted expense;
- capital transaction;
- ONT history;
- audit trail;
- network transaction history yang relevan.

Gunakan status/archive/reversal sesuai domain.

---

# 69. Security Requirements

Minimum:

- HTTPS untuk web;
- CSRF protection sesuai arsitektur authentication;
- secure cookies;
- Laravel validation;
- authorization per endpoint;
- rate limiting login;
- password hashing;
- SQL injection protection melalui ORM/query binding;
- XSS protection;
- audit trail;
- secrets tidak masuk repository;
- encrypted sensitive configuration;
- MikroTik IP whitelist;
- minimum MikroTik API permission;
- database backup;
- session security.

---

# 70. MikroTik Security

Karena API terekspos ke internet:

**Mandatory:**

- firewall source whitelist ke IP VPS;
- tidak expose API untuk seluruh internet;
- gunakan port/protocol aman yang didukung;
- API user khusus Aionios.NET;
- password kuat;
- permission minimum;
- monitoring failed connection;
- credential rotation capability.

Konfigurasi credential MikroTik tidak boleh ditampilkan kembali secara plaintext di UI setelah disimpan.

---

# 71. Backup Requirement

Aionios.NET harus memiliki mekanisme backup MariaDB.

Minimum target:

- scheduled database backup;
- backup retention configurable;
- manual backup oleh Owner;
- backup success/failure log.

Backup idealnya disimpan pada storage yang berbeda dari VPS utama sehingga kegagalan VPS tidak sekaligus menghilangkan seluruh backup.

Restore procedure harus didokumentasikan dan diuji secara berkala.

---

# 72. Performance Targets

Target awal:

- halaman umum < 2 detik pada kondisi normal;
- pencarian pelanggan responsif;
- tabel menggunakan server-side pagination untuk dataset besar;
- financial reports dapat diproses asynchronous jika dataset besar;
- MikroTik operation memiliki timeout;
- billing generation menggunakan queue/batch.

Tidak boleh melakukan generate seluruh invoice bulanan melalui satu HTTP request browser.

---

# 73. Reliability Requirements

Critical jobs harus:

- idempotent;
- retry-safe;
- logged;
- observable.

Contoh unique billing constraint:

**Customer + Billing Period**

tidak boleh menghasilkan invoice reguler ganda.

---

# 74. Key Business Rules

Business rules utama yang tidak boleh berubah tanpa product decision:

1. Invoice reguler terbit tanggal 1.
2. Due date tanggal 22.
3. Auto-isolation tanggal 23 pukul 01:00.
4. Pelanggan harus melunasi seluruh outstanding untuk aktif kembali.
5. Tidak ada partial payment.
6. Invoice berikutnya tetap terbit walaupun pelanggan isolated.
7. Pelanggan terminated tidak dihapus.
8. Terminated customer dapat direaktivasi.
9. Reactivation wajib outstanding = 0.
10. First invoice menggunakan prorata.
11. First invoice menggunakan harga normal.
12. Satu pelanggan hanya memiliki satu paket aktif.
13. Promo ditentukan manual Admin Jaringan.
14. Promo harga berlaku saat invoice berikutnya diterbitkan.
15. Existing invoice immutable.
16. Payment manual tidak memiliki MDR.
17. QRIS menggunakan configurable MDR.
18. Posted financial transaction immutable.
19. Reversal memerlukan Owner approval.
20. Operational expense memerlukan Owner approval.
21. Accounting period dapat dikunci Owner.
22. Network failure tidak boleh membatalkan payment yang valid.

---

# 75. MVP Scope

## Phase 1 / MVP

Wajib:

- Authentication
- RBAC
- Role Dashboard
- Customer Management
- Package Management
- PPPoE Management
- MikroTik Integration
- ONT Tracking
- Promo
- Billing
- Prorata
- Auto Isolation
- Auto Unisolation
- Manual Payment
- Manual QRIS
- MDR
- Payment Preview
- Payment Reversal
- Multi Cash/Bank
- COA
- Opening Balance
- Automatic Journal
- Manual Journal
- Other Income
- Operational Expense
- Owner Approval
- Capital
- General Ledger
- Trial Balance
- Income Statement
- Balance Sheet
- Cash Flow
- Changes in Equity
- Receivables
- Revenue Reports
- Accounting Period Lock
- PDF Export
- Internal Notification
- Audit Trail
- Network Job Queue.

---

# 76. Out of Scope — MVP

Belum masuk:

- SaaS/multi-company;
- multi-tenant;
- customer mobile app;
- customer self-service portal;
- WhatsApp gateway;
- SMS;
- email notification;
- automatic QRIS payment gateway;
- virtual account;
- credit card;
- partial payment;
- Excel export;
- general fixed asset management;
- depreciation;
- payroll;
- inventory selain kebutuhan ONT;
- multi-MikroTik production support;
- advanced tax automation;
- advanced PSAK-specific modules.

---

# 77. Future Roadmap

Kemungkinan fase berikutnya:

### Phase 2
- WhatsApp billing reminder;
- customer portal;
- automatic QRIS;
- payment gateway;
- reconciliation;
- richer analytics.

### Phase 3
- multi-router MikroTik;
- OLT integration;
- topology/network monitoring;
- automated provisioning;
- advanced customer network diagnostics.

### Phase 4
- fixed asset;
- depreciation;
- tax module;
- advanced PSAK accounting;
- budgeting;
- financial forecasting.

---

# 78. Critical Acceptance Criteria

Aionios.NET MVP dianggap memenuhi requirement inti apabila skenario berikut berhasil end-to-end:

### Customer Activation

Admin membuat pelanggan → memilih paket → memasang ONT → membuat PPPoE → PPP Secret berhasil dibuat di MikroTik → pelanggan aktif.

### Billing

Tanggal 1 → invoice otomatis dibuat tanpa duplikasi.

### Prorata

Pelanggan baru → invoice pertama dihitung otomatis berdasarkan tanggal aktivasi.

### Isolation

Tagihan belum lunas → tanggal 23 pukul 01:00 → PPP Profile menjadi `ISOLIR`.

### Payment

Admin membuka outstanding → memilih metode → melihat preview → konfirmasi → seluruh invoice lunas.

### QRIS

Sistem menghitung MDR berdasarkan konfigurasi → pelanggan dianggap membayar gross invoice → biaya MDR dan net settlement tercatat.

### Unisolation

Outstanding = 0 → pelanggan kembali ke profile normal/promo.

### Network Failure

Payment berhasil tetapi MikroTik offline → payment tetap posted → network action masuk pending sync.

### Expense

Admin Keuangan mengajukan expense → Owner approve → jurnal dan saldo kas/bank ter-update.

### Reversal

Admin meminta reversal → Owner approve → reversal journal dibuat tanpa menghapus transaksi asli.

### Closing

Owner menutup periode → transaksi backdated ke periode tersebut ditolak.

### Reporting

Ledger → Trial Balance → Income Statement → Balance Sheet dapat ditelusuri kembali ke source transaction.

---

# 79. Success Metrics

Setelah implementasi, target keberhasilan:

- ≥99% recurring invoice berhasil dibuat otomatis;
- tidak ada duplicate invoice;
- ≥99% scheduled isolation diproses atau tercatat sebagai retryable failure;
- 100% posted financial transaction memiliki journal/source reference;
- 100% reversal memiliki approval trail;
- 100% expense posted memiliki Owner approval;
- 100% perubahan sensitif memiliki audit trail;
- pengurangan signifikan pekerjaan billing manual;
- laporan keuangan bulanan dapat dihasilkan langsung dari sistem.

---

# 80. Definition of Done

Aionios.NET MVP dinyatakan siap produksi ketika:

- seluruh acceptance criteria kritis lulus;
- role permission telah diuji;
- billing scheduler telah diuji lintas pergantian bulan;
- isolir/unisolir MikroTik telah diuji;
- network failure/retry telah diuji;
- journal balancing telah diuji;
- closing period telah diuji;
- reversal telah diuji;
- QRIS MDR telah diuji;
- laporan keuangan telah direkonsiliasi;
- audit trail aktif;
- backup dan restore telah diuji;
- HTTPS aktif;
- MikroTik firewall whitelist aktif;
- production secrets aman;
- Owner melakukan UAT dan sign-off.

---

# 81. Product Vision

**Aionios.NET bukan hanya aplikasi billing ISP.**

Aionios.NET dirancang menjadi pusat operasional perusahaan yang menyatukan:

**Customer Management + ISP Billing + MikroTik Network Automation + Financial Management + Accounting**

dalam satu platform internal.

Arsitektur harus menjaga tiga prinsip utama:

**Financial Integrity** — transaksi finansial dapat diaudit dan tidak diubah diam-diam.

**Operational Automation** — billing, isolir, aktivasi, promo, dan jaringan sebisa mungkin berjalan otomatis.

**Traceability** — setiap perubahan pelanggan, ONT, jaringan, pembayaran, approval, dan jurnal dapat ditelusuri kembali ke sumbernya.

Dengan fondasi tersebut, Aionios.NET dapat dikembangkan bertahap dari sistem internal ISP menjadi platform operasional yang jauh lebih lengkap tanpa perlu merombak core billing dan accounting dari awal.