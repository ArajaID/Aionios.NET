<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function markAsRead(Notification $notification): RedirectResponse
    {
        $user = Auth::user();
        $canAccess = $notification->user_id !== null
            ? $notification->user_id === $user->id
            : ($notification->role !== null ? $notification->role === $user->role : true);

        abort_unless($canAccess, 403, 'Anda tidak memiliki akses ke notifikasi ini.');

        $notification->update(['is_read' => true]);
        if ($notification->link) {
            return redirect($notification->link);
        }
        return back();
    }

    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();
        Notification::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function ($roleQuery) use ($user) {
                    $roleQuery->whereNull('user_id')->where('role', $user->role);
                })
                ->orWhere(function ($globalQuery) {
                    $globalQuery->whereNull('user_id')->whereNull('role');
                });
        })->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }
}
