<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = ApplicationSetting::all()->pluck('value', 'key');
        return Inertia::render('Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'app_brand_name' => 'required|string|max:100',
            'company_address' => 'required|string',
            'company_phone' => 'required|string|max:100',
            'default_qris_mdr' => 'required|numeric|min:0|max:100',
            'invoice_due_day' => 'required|integer|min:1|max:28',
            'auto_isolate_day' => 'required|integer|min:1|max:28',
            'system_timezone' => 'required|string|max:100',
        ]);

        foreach ($validated as $key => $val) {
            ApplicationSetting::set($key, $val);
        }

        AuditService::log('update_settings', 'settings', null, null, null, $validated);

        return back()->with('success', 'Konfigurasi aplikasi berhasil diperbarui.');
    }
}
