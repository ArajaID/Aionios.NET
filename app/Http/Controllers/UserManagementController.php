<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    private const ROLES = ['owner', 'admin_keuangan', 'admin_jaringan'];

    public function index(Request $request): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at'])
                ->map(fn (User $user) => [
                    ...$user->toArray(),
                    'is_current_user' => $user->is($request->user()),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ]);

        $user = User::create($validated);

        AuditService::log('create_user', 'users', User::class, $user->id, null, $this->auditValues($user));

        return back()->with('success', "Akun {$user->name} berhasil dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user()) && ($validated['role'] !== 'owner' || !$validated['is_active'])) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat menurunkan role atau menonaktifkan akun yang sedang digunakan.',
            ]);
        }

        $this->ensureActiveOwnerRemains($user, $validated['role'], $validated['is_active']);

        $oldValues = $this->auditValues($user);
        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        if (!$user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditService::log('update_user', 'users', User::class, $user->id, $oldValues, $this->auditValues($user));

        return back()->with('success', "Akun {$user->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $this->ensureActiveOwnerRemains($user, $user->role, false);

        $oldValues = $this->auditValues($user);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        AuditService::log('delete_user', 'users', User::class, $user->id, $oldValues);

        return back()->with('success', "Akun {$user->name} berhasil dihapus.");
    }

    private function ensureActiveOwnerRemains(User $user, string $newRole, bool $newStatus): void
    {
        $removesActiveOwner = $user->role === 'owner'
            && $user->is_active
            && ($newRole !== 'owner' || !$newStatus);

        if ($removesActiveOwner && User::where('role', 'owner')->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Minimal satu akun owner aktif harus tetap tersedia.',
            ]);
        }
    }

    private function auditValues(User $user): array
    {
        return $user->only(['name', 'email', 'phone', 'role', 'is_active']);
    }
}
