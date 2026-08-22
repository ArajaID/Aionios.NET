<?php

namespace App\Http\Middleware;

use App\Models\ApplicationSetting;
use App\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = [];
        $unreadCount = 0;

        if ($user) {
            $notificationsQuery = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('role', $user->role)
                    ->orWhereNull('role');
            })->latest()->take(10);

            $notifications = $notificationsQuery->get();
            $unreadCount = Notification::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('role', $user->role)
                    ->orWhereNull('role');
            })->where('is_read', false)->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'phone' => $user->phone,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app_settings' => [
                'brand_name' => ApplicationSetting::get('app_brand_name', 'Aionios.NET'),
                'company_name' => ApplicationSetting::get('company_name', 'PT Aionios Solusi Telematika'),
                'default_qris_mdr' => ApplicationSetting::get('default_qris_mdr', '0.7'),
                'timezone' => ApplicationSetting::get('system_timezone', 'Asia/Jakarta'),
            ],
            'notifications' => $notifications,
            'unread_notifications_count' => $unreadCount,
        ];
    }
}
