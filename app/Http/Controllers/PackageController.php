<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\AuditService;
use App\Services\MikrotikService;
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

    public function destroy(Package $package, MikrotikService $mikrotikService): RedirectResponse
    {
        if ($package->customers()->exists()) {
            return back()->with('error', 'Paket tidak dapat dihapus karena masih digunakan pelanggan. Pindahkan seluruh pelanggan ke paket lain terlebih dahulu.');
        }

        $oldValues = $package->toArray();
        $profileUsedByAnotherPackage = Package::where('id', '!=', $package->id)
            ->where('ppp_profile', $package->ppp_profile)
            ->exists();

        $mikrotikMessage = null;
        if (!$profileUsedByAnotherPackage) {
            $result = $mikrotikService->deletePppProfile($package->ppp_profile);
            if (!$result['success']) {
                return back()->with('error', $result['message'] . ' Paket belum dihapus dari database.');
            }
            $mikrotikMessage = $result['message'];
        }

        $package->delete();

        AuditService::log('delete_package', 'packages', 'Package', $oldValues['id'], $oldValues, [
            'deleted' => true,
            'mikrotik_profile_deleted' => !$profileUsedByAnotherPackage,
        ]);

        $message = "Paket {$oldValues['code']} berhasil dihapus.";
        if ($profileUsedByAnotherPackage) {
            $message .= " PPP Profile {$oldValues['ppp_profile']} dipertahankan karena masih digunakan paket lain.";
        } elseif ($mikrotikMessage) {
            $message .= " {$mikrotikMessage}";
        }

        return back()->with('success', $message);
    }
}
