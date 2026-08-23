<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Models\AccountMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $existingOwner = User::withTrashed()
            ->where('email', 'jamil@aionios.net')
            ->first();

        if ($existingOwner?->trashed()) {
            $existingOwner->restore();
        }

        User::updateOrCreate(
            ['email' => 'jamil@aionios.net'],
            [
                'name' => 'Abdul Rahman Jamil',
                'password' => Hash::make('aioniosisthebest'),
                'role' => 'owner',
                'is_active' => true,
            ]
        );

        $settings = [
            ['key' => 'company_name', 'value' => 'Aionios.NET', 'description' => 'Nama Legal Perusahaan'],
            ['key' => 'app_brand_name', 'value' => 'Aionios.NET', 'description' => 'Nama Brand Sistem ISP'],
            ['key' => 'company_address', 'value' => 'Cyber Building 2 Lt. 12, Jl. HR Rasuna Said, Jakarta', 'description' => 'Alamat Perusahaan'],
            ['key' => 'company_phone', 'value' => '(021) 555-0199 / 0812-9988-7766', 'description' => 'Nomor Kontak Layanan'],
            ['key' => 'default_qris_mdr', 'value' => '0.7', 'description' => 'Tarif Default MDR QRIS (%)'],
            ['key' => 'invoice_due_day', 'value' => '22', 'description' => 'Tanggal Jatuh Tempo Tagihan Bulanan'],
            ['key' => 'auto_isolate_day', 'value' => '23', 'description' => 'Tanggal Eksekusi Isolir Otomatis'],
            ['key' => 'auto_isolate_time', 'value' => '01:00', 'description' => 'Waktu Eksekusi Isolir Otomatis'],
            ['key' => 'auto_isolate_enabled', 'value' => '1', 'description' => 'Status Isolir Otomatis'],
            ['key' => 'system_timezone', 'value' => 'Asia/Jakarta', 'description' => 'Zona Waktu Operasional'],
        ];

        foreach ($settings as $setting) {
            ApplicationSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                ]
            );
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
            $created = ChartOfAccount::firstOrCreate(['code' => $c['code']], $c);
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
            AccountMapping::updateOrCreate(
                ['purpose' => $p],
                ['chart_of_account_id' => $aid]
            );
        }
    }
}
