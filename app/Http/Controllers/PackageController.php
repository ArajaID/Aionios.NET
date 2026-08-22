<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        $packages = Package::withCount('customers')->latest()->get();
        return Inertia::render('Packages/Index', ['packages' => $packages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:packages,code|max:50',
            'name' => 'required|string|max:255',
            'download_speed_mbps' => 'required|integer|min:1',
            'upload_speed_mbps' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'ppp_profile' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $package = Package::create($validated);
        AuditService::log('create_package', 'packages', 'Package', $package->id, null, $package->toArray());

        return back()->with('success', 'Paket internet baru berhasil ditambahkan.');
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'download_speed_mbps' => 'required|integer|min:1',
            'upload_speed_mbps' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'ppp_profile' => 'required|string|max:100',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $old = $package->toArray();
        $package->update($validated);

        AuditService::log('update_package', 'packages', 'Package', $package->id, $old, $package->toArray());

        return back()->with('success', 'Data paket internet berhasil diperbarui. Perubahan harga akan berlaku pada billing periode berikutnya.');
    }
}
