<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Models\User;
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
    }
}
