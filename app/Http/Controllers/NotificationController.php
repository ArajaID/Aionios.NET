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
                ->orWhere('role', $user->role)
                ->orWhereNull('role');
        })->where('is_read', false)->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }
}
