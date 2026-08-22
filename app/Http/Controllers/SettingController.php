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
            'brand_name' => 'required|string|max:100',
            'default_qris_mdr' => 'required|numeric|min:0|max:100',
            'invoice_due_day' => 'required|integer|min:1|max:28',
            'auto_isolate_day' => 'required|integer|min:1|max:28',
            'auto_isolate_time' => 'required|date_format:H:i',
            'auto_isolate_enabled' => 'required|boolean',
        ]);

        $applicationSettings = [
            'app_brand_name' => $validated['brand_name'],
            'default_qris_mdr' => $validated['default_qris_mdr'],
            'invoice_due_day' => $validated['invoice_due_day'],
            'auto_isolate_day' => $validated['auto_isolate_day'],
            'auto_isolate_time' => $validated['auto_isolate_time'],
            'auto_isolate_enabled' => $validated['auto_isolate_enabled'],
        ];

        foreach ($applicationSettings as $key => $val) {
            ApplicationSetting::set($key, $val);
        }

        AuditService::log('update_settings', 'settings', null, null, null, $applicationSettings);

        return back()->with('success', 'Konfigurasi aplikasi berhasil diperbarui.');
    }
}
