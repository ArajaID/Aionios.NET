<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\ApplicationSetting;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPromotion;
use App\Models\CustomerStatusHistory;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\MikrotikRouter;
use App\Models\Notification;
use App\Models\Ont;
use App\Models\OntHistory;
use App\Models\OtherIncome;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PppAccount;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Users
        $owner = User::create([
            'name' => 'Abdul Rahman Jamil',
            'email' => 'jamil@aionios.net',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $finance = User::create([
            'name' => 'Siti Rahma (Admin Keuangan)',
            'email' => 'finance@aionios.net',
            'password' => Hash::make('password'),
            'role' => 'admin_keuangan',
            'phone' => '081234567891',
            'is_active' => true,
        ]);

        $network = User::create([
            'name' => 'Andi Wijaya (Admin Jaringan)',
            'email' => 'network@aionios.net',
            'password' => Hash::make('password'),
            'role' => 'admin_jaringan',
            'phone' => '081234567892',
            'is_active' => true,
        ]);

        // 2. Application Settings
        $settings = [
            ['key' => 'company_name', 'value' => 'Aionios.NET', 'description' => 'Nama Legal Perusahaan'],
            ['key' => 'app_brand_name', 'value' => 'Aionios.NET', 'description' => 'Nama Brand Sistem ISP'],
            ['key' => 'company_address', 'value' => 'Cyber Building 2 Lt. 12, Jl. HR Rasuna Said, Jakarta', 'description' => 'Alamat Perusahaan'],
            ['key' => 'company_phone', 'value' => '(021) 555-0199 / 0812-9988-7766', 'description' => 'Nomor Kontak Layanan'],
            ['key' => 'default_qris_mdr', 'value' => '0.7', 'description' => 'Tarif Default MDR QRIS (%)'],
            ['key' => 'invoice_due_day', 'value' => '22', 'description' => 'Tanggal Jatuh Tempo Tagihan Bulanan'],
            ['key' => 'auto_isolate_day', 'value' => '23', 'description' => 'Tanggal Eksekusi Isolir Otomatis'],
            ['key' => 'system_timezone', 'value' => 'Asia/Jakarta', 'description' => 'Zona Waktu Operasional'],
        ];
        foreach ($settings as $s) {
            ApplicationSetting::create($s);
        }

        // 3. Chart of Accounts (COA ISP Standard)
        $coas = [
            ['code' => '1110', 'name' => 'Kas Kasir Utama', 'type' => 'asset', 'category' => 'Kas & Setara Kas', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1120', 'name' => 'Bank BCA Operasional', 'type' => 'asset', 'category' => 'Kas & Setara Kas', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1130', 'name' => 'Bank BRI Penerimaan', 'type' => 'asset', 'category' => 'Kas & Setara Kas', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1140', 'name' => 'QRIS Settlement Merchant', 'type' => 'asset', 'category' => 'Kas & Setara Kas', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1210', 'name' => 'Piutang Usaha Pelanggan', 'type' => 'asset', 'category' => 'Piutang', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '1310', 'name' => 'Persediaan ONT & Router', 'type' => 'asset', 'category' => 'Persediaan', 'normal_balance' => 'debit', 'is_system' => false],
            ['code' => '2110', 'name' => 'Hutang Usaha / Vendor', 'type' => 'liability', 'category' => 'Kewajiban Jangka Pendek', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '3110', 'name' => 'Modal Pemilik', 'type' => 'equity', 'category' => 'Ekuitas', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '3210', 'name' => 'Prive Pemilik', 'type' => 'equity', 'category' => 'Ekuitas', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '3310', 'name' => 'Saldo Laba Ditahan', 'type' => 'equity', 'category' => 'Ekuitas', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '4110', 'name' => 'Pendapatan Langganan Internet', 'type' => 'revenue', 'category' => 'Pendapatan Usaha', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '4210', 'name' => 'Pendapatan Instalasi & Lain-lain', 'type' => 'revenue', 'category' => 'Pendapatan Lain', 'normal_balance' => 'credit', 'is_system' => true],
            ['code' => '5110', 'name' => 'Beban Bandwidth & Upstream', 'type' => 'expense', 'category' => 'Beban Pokok Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5120', 'name' => 'Beban Listrik & POP Shelter', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5130', 'name' => 'Beban Gaji & Tunjangan Tim', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5140', 'name' => 'Beban Pemeliharaan Jaringan & Fiber', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5150', 'name' => 'Beban Transportasi & Kendaraan Tim', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5160', 'name' => 'Beban Sewa Tiang & Jalur Kabel', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5170', 'name' => 'Beban MDR QRIS', 'type' => 'expense', 'category' => 'Beban Keuangan', 'normal_balance' => 'debit', 'is_system' => true],
            ['code' => '5180', 'name' => 'Beban Operasional Lain-lain', 'type' => 'expense', 'category' => 'Beban Operasional', 'normal_balance' => 'debit', 'is_system' => true],
        ];

        $coaMap = [];
        foreach ($coas as $c) {
            $created = ChartOfAccount::create($c);
            $coaMap[$c['code']] = $created;
        }

        // Account Mappings
        $mappings = [
            'cash_default' => $coaMap['1110']->id,
            'bank_default' => $coaMap['1120']->id,
            'ar_internet' => $coaMap['1210']->id,
            'revenue_internet' => $coaMap['4110']->id,
            'revenue_other' => $coaMap['4210']->id,
            'expense_mdr' => $coaMap['5170']->id,
            'equity_capital' => $coaMap['3110']->id,
            'equity_retained_earnings' => $coaMap['3310']->id,
            'equity_drawing' => $coaMap['3210']->id,
        ];
        foreach ($mappings as $p => $aid) {
            AccountMapping::create(['purpose' => $p, 'chart_of_account_id' => $aid]);
        }

        // Cash & Bank Accounts
        $cbKas = CashBankAccount::create([
            'name' => 'Kas Tunai Kasir',
            'account_number' => 'CASH-01',
            'bank_name' => 'Internal Cash',
            'chart_of_account_id' => $coaMap['1110']->id,
            'opening_balance' => 5000000,
            'current_balance' => 5000000,
        ]);

        $cbBca = CashBankAccount::create([
            'name' => 'BCA Bisnis Operasional',
            'account_number' => '8830-192-881',
            'bank_name' => 'Bank Central Asia',
            'chart_of_account_id' => $coaMap['1120']->id,
            'opening_balance' => 45000000,
            'current_balance' => 45000000,
        ]);

        $cbBri = CashBankAccount::create([
            'name' => 'BRI Penerimaan Pelanggan',
            'account_number' => '0341-010-09823',
            'bank_name' => 'Bank Rakyat Indonesia',
            'chart_of_account_id' => $coaMap['1130']->id,
            'opening_balance' => 20000000,
            'current_balance' => 20000000,
        ]);

        $cbQris = CashBankAccount::create([
            'name' => 'QRIS Settlement Bank',
            'account_number' => 'QRIS-MERCH-882',
            'bank_name' => 'QRIS Settlement (BCA/NMID)',
            'chart_of_account_id' => $coaMap['1140']->id,
            'opening_balance' => 12500000,
            'current_balance' => 12500000,
        ]);

        // Opening Balance Journal Entry
        $openingTotal = 5000000 + 45000000 + 20000000 + 12500000;
        $openEntry = JournalEntry::create([
            'entry_number' => 'JRN-20260101-0001',
            'date' => Carbon::create(2026, 1, 1),
            'reference_type' => 'opening_balance',
            'description' => 'Saldo Awal Migrasi Sistem Aionios.NET',
            'status' => 'posted',
            'created_by' => $owner->id,
            'is_balanced' => true,
        ]);

        JournalLine::create(['journal_entry_id' => $openEntry->id, 'chart_of_account_id' => $coaMap['1110']->id, 'debit' => 5000000, 'credit' => 0, 'memo' => 'Saldo awal Kas Tunai']);
        JournalLine::create(['journal_entry_id' => $openEntry->id, 'chart_of_account_id' => $coaMap['1120']->id, 'debit' => 45000000, 'credit' => 0, 'memo' => 'Saldo awal Bank BCA']);
        JournalLine::create(['journal_entry_id' => $openEntry->id, 'chart_of_account_id' => $coaMap['1130']->id, 'debit' => 20000000, 'credit' => 0, 'memo' => 'Saldo awal Bank BRI']);
        JournalLine::create(['journal_entry_id' => $openEntry->id, 'chart_of_account_id' => $coaMap['1140']->id, 'debit' => 12500000, 'credit' => 0, 'memo' => 'Saldo awal QRIS Settlement']);
        JournalLine::create(['journal_entry_id' => $openEntry->id, 'chart_of_account_id' => $coaMap['3110']->id, 'debit' => 0, 'credit' => $openingTotal, 'memo' => 'Modal Pemilik - Saldo Awal']);

        // 4. Internet Packages
        $pkg10 = Package::create([
            'code' => 'PKG-10M',
            'name' => 'Home Lite 10 Mbps',
            'download_speed_mbps' => 10,
            'upload_speed_mbps' => 10,
            'price' => 150000,
            'ppp_profile' => 'PROFILE-10M',
            'is_active' => true,
            'description' => 'Paket ekonomis streaming & browsing 1-3 perangkat.',
        ]);

        $pkg20 = Package::create([
            'code' => 'PKG-20M',
            'name' => 'Home Family 20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 20,
            'price' => 250000,
            'ppp_profile' => 'PROFILE-20M',
            'is_active' => true,
            'description' => 'Paket keluarga ideal game & streaming HD 3-5 perangkat.',
        ]);

        $pkg30 = Package::create([
            'code' => 'PKG-30M',
            'name' => 'Home Ultra 30 Mbps',
            'download_speed_mbps' => 30,
            'upload_speed_mbps' => 30,
            'price' => 350000,
            'ppp_profile' => 'PROFILE-30M',
            'is_active' => true,
            'description' => 'Paket ultra cepat 4K streaming dan gaming tanpa hambatan.',
        ]);

        $pkg50 = Package::create([
            'code' => 'PKG-50M',
            'name' => 'Home Pro 50 Mbps',
            'download_speed_mbps' => 50,
            'upload_speed_mbps' => 50,
            'price' => 500000,
            'ppp_profile' => 'PROFILE-50M',
            'is_active' => true,
            'description' => 'Koneksi prioritas untuk work from home intensif & creator.',
        ]);

        $pkg100 = Package::create([
            'code' => 'PKG-100M',
            'name' => 'Business Dedicated 100 Mbps',
            'download_speed_mbps' => 100,
            'upload_speed_mbps' => 100,
            'price' => 1000000,
            'ppp_profile' => 'PROFILE-100M',
            'is_active' => true,
            'description' => 'SLA 99.5% untuk kantor, kafe, dan unit usaha komersial.',
        ]);

        // 5. Promotions
        $promoBoost = Promotion::create([
            'code' => 'PROMO-BOOST30',
            'name' => 'Speed Boost 30M Harga 20M',
            'type' => 'speed_boost',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'duration_months' => 3,
            'promo_ppp_profile' => 'PROFILE-30M',
            'is_active' => true,
            'description' => 'Mendapatkan speed 30 Mbps dengan tarif normal 20 Mbps selama 3 bulan.',
        ]);

        $promoDiscount = Promotion::create([
            'code' => 'PROMO-HEMAT50K',
            'name' => 'Diskon Spesial Rp50.000',
            'type' => 'special_discount',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'duration_months' => 2,
            'is_active' => true,
            'description' => 'Potongan langsung Rp 50.000 per bulan pada billing reguler.',
        ]);

        $promoPriceCut = Promotion::create([
            'code' => 'PROMO-CUT200',
            'name' => 'Paket 20M Khusus Rp200.000',
            'type' => 'price_cut',
            'discount_type' => 'fixed',
            'discount_value' => 200000,
            'duration_months' => 3,
            'is_active' => true,
            'description' => 'Harga spesial Rp 200.000 per bulan selama 3 bulan pertama.',
        ]);

        // 6. Router MikroTik
        $router = MikrotikRouter::create([
            'name' => 'MikroTik CCR2004 Utama',
            'host' => '103.144.20.10',
            'port' => 8728,
            'username' => 'aionios_api',
            'password' => 'RouterSecurePass2026!',
            'api_type' => 'rest',
            'is_active' => true,
            'status' => 'online',
            'last_connected_at' => now(),
        ]);

        // 7. ONTs Inventory
        $ontModels = [
            ['brand' => 'Huawei', 'model' => 'HG8245H5', 'prefix' => 'HWTC'],
            ['brand' => 'ZTE', 'model' => 'ZXHN F609 V3', 'prefix' => 'ZTEG'],
            ['brand' => 'Fiberhome', 'model' => 'HG680P', 'prefix' => 'FHTT'],
        ];

        $ontList = [];
        for ($i = 1; $i <= 15; $i++) {
            $m = $ontModels[$i % 3];
            $ont = Ont::create([
                'ont_id' => 'ONT-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'brand' => $m['brand'],
                'model' => $m['model'],
                'serial_number' => $m['prefix'] . strtoupper(substr(md5($i . 'aionios'), 0, 8)),
                'mac_address' => sprintf('48:EE:0C:%02X:%02X:%02X', ($i * 7) % 255, ($i * 13) % 255, ($i * 19) % 255),
                'status' => 'available',
                'condition' => 'good',
                'notes' => 'Stok perangkat siap pasang.',
            ]);
            $ontList[] = $ont;
        }

        // 8. Customers & PPP Accounts
        $customerData = [
            ['name' => 'Ahmad Fauzi', 'phone' => '081289123401', 'address' => 'Jl. Mawar No. 12, RT 01/RW 03, Jakarta Selatan', 'pkg' => $pkg20, 'status' => 'active', 'install' => '2026-01-10', 'promo' => $promoBoost],
            ['name' => 'Dewi Lestari', 'phone' => '081289123402', 'address' => 'Komplek Permata Hijau Blok C3/15', 'pkg' => $pkg30, 'status' => 'active', 'install' => '2026-01-15', 'promo' => null],
            ['name' => 'Bambang Kusuma', 'phone' => '081289123403', 'address' => 'Jl. Kebon Jeruk Raya No. 45B', 'pkg' => $pkg10, 'status' => 'active', 'install' => '2026-02-01', 'promo' => $promoDiscount],
            ['name' => 'Rina Marlina', 'phone' => '081289123404', 'address' => 'Apartemen Sudirman Tower B No. 1402', 'pkg' => $pkg50, 'status' => 'active', 'install' => '2026-02-10', 'promo' => null],
            ['name' => 'Hendra Gunawan', 'phone' => '081289123405', 'address' => 'Ruko Golden Boulevard Blok A-8 (Kafe Kopi Kita)', 'pkg' => $pkg100, 'status' => 'active', 'install' => '2026-03-01', 'promo' => null],
            ['name' => 'Maya Indrawati', 'phone' => '081289123406', 'address' => 'Jl. Cempaka Putih Tengah No. 88', 'pkg' => $pkg20, 'status' => 'active', 'install' => '2026-03-15', 'promo' => null],
            ['name' => 'Eko Prasetyo', 'phone' => '081289123407', 'address' => 'Jl. Tebet Barat Dalam VII No. 22', 'pkg' => $pkg20, 'status' => 'isolated', 'install' => '2026-04-01', 'promo' => null], // Isolated
            ['name' => 'Mega Suryani', 'phone' => '081289123408', 'address' => 'Jl. Pejaten Barat Raya No. 19', 'pkg' => $pkg30, 'status' => 'isolated', 'install' => '2026-04-10', 'promo' => null], // Isolated
            ['name' => 'Dodi Darmawan', 'phone' => '081289123409', 'address' => 'Jl. Rawamangun Muka Timur No. 5', 'pkg' => $pkg10, 'status' => 'terminated', 'install' => '2026-01-05', 'promo' => null], // Terminated
            ['name' => 'Fitri Handayani', 'phone' => '081289123410', 'address' => 'Jl. Kemang Raya No. 104', 'pkg' => $pkg20, 'status' => 'active', 'install' => '2026-05-18', 'promo' => $promoPriceCut],
        ];

        foreach ($customerData as $idx => $cd) {
            $custId = 'CUST-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);
            $ont = $ontList[$idx];

            $cust = Customer::create([
                'customer_id' => $custId,
                'name' => $cd['name'],
                'phone' => $cd['phone'],
                'address' => $cd['address'],
                'installed_at' => $cd['install'],
                'activated_at' => $cd['install'],
                'package_id' => $cd['pkg']->id,
                'ont_id' => $ont->id,
                'status' => $cd['status'],
                'notes' => 'Pelanggan terdaftar.',
            ]);

            // Assign ONT
            $ont->update([
                'status' => $cd['status'] === 'terminated' ? 'returned' : 'installed',
                'current_customer_id' => $cd['status'] === 'terminated' ? null : $cust->id,
                'installed_at' => $cd['install'],
            ]);

            OntHistory::create([
                'ont_id' => $ont->id,
                'customer_id' => $cust->id,
                'action' => 'assigned',
                'condition' => 'good',
                'admin_id' => $network->id,
                'notes' => "Pemasangan awal di lokasi pelanggan {$cust->name}.",
            ]);

            // Customer Status History
            CustomerStatusHistory::create([
                'customer_id' => $cust->id,
                'old_status' => 'pending',
                'new_status' => 'active',
                'reason' => 'Aktivasi pemasangan baru',
                'changed_by' => $network->id,
            ]);

            if ($cd['status'] === 'isolated') {
                CustomerStatusHistory::create([
                    'customer_id' => $cust->id,
                    'old_status' => 'active',
                    'new_status' => 'isolated',
                    'reason' => 'Tunggakan tagihan melewati tanggal 22 (Auto-Isolation)',
                    'changed_by' => $network->id,
                ]);
            } elseif ($cd['status'] === 'terminated') {
                CustomerStatusHistory::create([
                    'customer_id' => $cust->id,
                    'old_status' => 'active',
                    'new_status' => 'terminated',
                    'reason' => 'Pelanggan pindah domisili',
                    'changed_by' => $network->id,
                ]);
            }

            // PPP Account
            $pppProfile = $cd['status'] === 'isolated' ? 'ISOLIR' : ($cd['promo'] && $cd['promo']->promo_ppp_profile ? $cd['promo']->promo_ppp_profile : $cd['pkg']->ppp_profile);
            $pppStatus = match ($cd['status']) {
                'active' => 'connected',
                'isolated' => 'isolated',
                'terminated' => 'disabled',
            };

            PppAccount::create([
                'customer_id' => $cust->id,
                'username' => 'user_' . strtolower(str_replace(' ', '', $cust->name)) . '@aionios',
                'password' => 'secret123',
                'profile' => $pppProfile,
                'is_active' => $cd['status'] !== 'terminated',
                'status' => $pppStatus,
                'current_ip' => $cd['status'] === 'active' ? '10.10.' . ($idx + 1) . '.2' : null,
                'last_sync_at' => now(),
            ]);

            // Assign Promotion if any
            if ($cd['promo']) {
                CustomerPromotion::create([
                    'customer_id' => $cust->id,
                    'promotion_id' => $cd['promo']->id,
                    'start_date' => Carbon::parse($cd['install']),
                    'end_date' => Carbon::parse($cd['install'])->addMonths($cd['promo']->duration_months),
                    'original_ppp_profile' => $cd['pkg']->ppp_profile,
                    'status' => 'active',
                ]);
            }

            // Invoices & Payment Samples
            $this->seedInvoicesForCustomer($cust, $cd, $finance, $cbBca, $cbQris, $coaMap);
        }

        // 9. Operational Expenses
        $expense1 = Expense::create([
            'expense_number' => 'EXP-20260801-0001',
            'date' => '2026-08-01',
            'chart_of_account_id' => $coaMap['5110']->id, // Bandwidth
            'cash_bank_account_id' => $cbBca->id,
            'amount' => 15000000,
            'description' => 'Pembayaran Upstream Bandwidth Tier-1 1 Gbps Agustus 2026',
            'status' => 'approved',
            'submitted_by' => $finance->id,
            'approved_by' => $owner->id,
            'approved_at' => Carbon::create(2026, 8, 2, 10, 0),
        ]);

        $cbBca->decrement('current_balance', 15000000);

        $jExp = JournalEntry::create([
            'entry_number' => 'JRN-20260802-0001',
            'date' => Carbon::create(2026, 8, 2),
            'reference_type' => 'expense',
            'reference_id' => $expense1->id,
            'description' => 'Beban Bandwidth Upstream Agustus 2026',
            'status' => 'posted',
            'created_by' => $owner->id,
            'is_balanced' => true,
        ]);
        JournalLine::create(['journal_entry_id' => $jExp->id, 'chart_of_account_id' => $coaMap['5110']->id, 'debit' => 15000000, 'credit' => 0, 'memo' => 'Beban Bandwidth 1 Gbps']);
        JournalLine::create(['journal_entry_id' => $jExp->id, 'chart_of_account_id' => $cbBca->chart_of_account_id, 'debit' => 0, 'credit' => 15000000, 'memo' => 'Kredit Bank BCA']);

        $expensePending = Expense::create([
            'expense_number' => 'EXP-20260815-0002',
            'date' => '2026-08-15',
            'chart_of_account_id' => $coaMap['5140']->id, // Maintenance
            'cash_bank_account_id' => $cbKas->id,
            'amount' => 1850000,
            'description' => 'Penggantian Patchcord & Splice Closure Tiang Fiber Sektor Pejaten',
            'status' => 'pending',
            'submitted_by' => $finance->id,
            'notes' => 'Mohon approval Owner untuk pembayaran maintenance kabel optik.',
        ]);

        // 10. Other Income
        OtherIncome::create([
            'income_number' => 'INC-20260810-0001',
            'date' => '2026-08-10',
            'chart_of_account_id' => $coaMap['4210']->id,
            'cash_bank_account_id' => $cbKas->id,
            'amount' => 750000,
            'description' => 'Biaya Registrasi & Penarikan Dropcore Baru 3 Lokasi',
            'reference' => 'INST-202608-01',
            'created_by' => $finance->id,
        ]);
        $cbKas->increment('current_balance', 750000);

        // 11. Internal Notifications
        Notification::create([
            'role' => 'owner',
            'type' => 'warning',
            'title' => 'Pengajuan Pengeluaran Menunggu Approval',
            'message' => 'Admin Keuangan mengajukan pengeluaran EXP-20260815-0002 sebesar Rp 1.850.000 untuk maintenance jaringan.',
            'link' => '/approvals',
        ]);

        Notification::create([
            'role' => 'admin_jaringan',
            'type' => 'danger',
            'title' => 'Auto-Isolation Berhasil',
            'message' => '2 pelanggan berhasil dipindahkan ke PPP Profile ISOLIR karena menunggak tagihan.',
            'link' => '/customers',
        ]);
    }

    protected function seedInvoicesForCustomer($cust, $cd, $finance, $cbBca, $cbQris, $coaMap): void
    {
        $currentPeriod = '2026-08';
        $prevPeriod = '2026-07';
        $pkgPrice = (float) $cd['pkg']->price;

        if ($cd['status'] === 'active') {
            // Paid previous invoice
            $invPrev = Invoice::create([
                'invoice_number' => 'INV-202607-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'period' => $prevPeriod,
                'issue_date' => Carbon::create(2026, 7, 1),
                'due_date' => Carbon::create(2026, 7, 22),
                'subtotal' => $pkgPrice,
                'discount_amount' => 0,
                'total_amount' => $pkgPrice,
                'paid_amount' => $pkgPrice,
                'status' => 'paid',
                'is_prorata' => false,
                'snapshot_data' => ['package_name' => $cd['pkg']->name, 'price' => $pkgPrice],
            ]);

            $pay = Payment::create([
                'payment_number' => 'PAY-20260715-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'payment_date' => Carbon::create(2026, 7, 15),
                'payment_method' => 'qris',
                'cash_bank_account_id' => $cbQris->id,
                'gross_amount' => $pkgPrice,
                'mdr_percentage' => 0.7,
                'mdr_fee' => round(($pkgPrice * 0.7) / 100, 2),
                'net_amount' => round($pkgPrice - (($pkgPrice * 0.7) / 100), 2),
                'notes' => 'Pembayaran via QRIS manual di kasir',
                'status' => 'posted',
                'received_by' => $finance->id,
            ]);

            PaymentAllocation::create([
                'payment_id' => $pay->id,
                'invoice_id' => $invPrev->id,
                'amount' => $pkgPrice,
            ]);

            // Current Month Invoice (Paid or Unpaid)
            if ($cust->id % 2 === 0) {
                // Paid current month via manual BCA
                $invCurr = Invoice::create([
                    'invoice_number' => 'INV-202608-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                    'customer_id' => $cust->id,
                    'period' => $currentPeriod,
                    'issue_date' => Carbon::create(2026, 8, 1),
                    'due_date' => Carbon::create(2026, 8, 22),
                    'subtotal' => $pkgPrice,
                    'discount_amount' => 0,
                    'total_amount' => $pkgPrice,
                    'paid_amount' => $pkgPrice,
                    'status' => 'paid',
                    'is_prorata' => false,
                    'snapshot_data' => ['package_name' => $cd['pkg']->name, 'price' => $pkgPrice],
                ]);

                $payCurr = Payment::create([
                    'payment_number' => 'PAY-20260805-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                    'customer_id' => $cust->id,
                    'payment_date' => Carbon::create(2026, 8, 5),
                    'payment_method' => 'manual',
                    'cash_bank_account_id' => $cbBca->id,
                    'gross_amount' => $pkgPrice,
                    'mdr_percentage' => 0,
                    'mdr_fee' => 0,
                    'net_amount' => $pkgPrice,
                    'notes' => 'Transfer manual Bank BCA',
                    'status' => 'posted',
                    'received_by' => $finance->id,
                ]);

                PaymentAllocation::create([
                    'payment_id' => $payCurr->id,
                    'invoice_id' => $invCurr->id,
                    'amount' => $pkgPrice,
                ]);
            } else {
                // Unpaid current month (not overdue yet)
                Invoice::create([
                    'invoice_number' => 'INV-202608-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                    'customer_id' => $cust->id,
                    'period' => $currentPeriod,
                    'issue_date' => Carbon::create(2026, 8, 1),
                    'due_date' => Carbon::create(2026, 8, 22),
                    'subtotal' => $pkgPrice,
                    'discount_amount' => 0,
                    'total_amount' => $pkgPrice,
                    'paid_amount' => 0,
                    'status' => 'unpaid',
                    'is_prorata' => false,
                    'snapshot_data' => ['package_name' => $cd['pkg']->name, 'price' => $pkgPrice],
                ]);
            }
        } elseif ($cd['status'] === 'isolated') {
            // 2 Outstanding overdue invoices
            Invoice::create([
                'invoice_number' => 'INV-202607-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'period' => $prevPeriod,
                'issue_date' => Carbon::create(2026, 7, 1),
                'due_date' => Carbon::create(2026, 7, 22),
                'subtotal' => $pkgPrice,
                'discount_amount' => 0,
                'total_amount' => $pkgPrice,
                'paid_amount' => 0,
                'status' => 'overdue',
                'is_prorata' => false,
                'snapshot_data' => ['package_name' => $cd['pkg']->name, 'price' => $pkgPrice],
            ]);

            Invoice::create([
                'invoice_number' => 'INV-202608-' . str_pad($cust->id, 4, '0', STR_PAD_LEFT),
                'customer_id' => $cust->id,
                'period' => $currentPeriod,
                'issue_date' => Carbon::create(2026, 8, 1),
                'due_date' => Carbon::create(2026, 8, 22),
                'subtotal' => $pkgPrice,
                'discount_amount' => 0,
                'total_amount' => $pkgPrice,
                'paid_amount' => 0,
                'status' => 'unpaid',
                'is_prorata' => false,
                'snapshot_data' => ['package_name' => $cd['pkg']->name, 'price' => $pkgPrice],
            ]);
        }
    }
}
