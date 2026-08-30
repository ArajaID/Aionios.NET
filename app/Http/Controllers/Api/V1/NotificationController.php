<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Notifikasi (Notifications)
 */
class NotificationController extends Controller
{
    /**
     * Daftar Notifikasi Pengguna
     *
     * Menampilkan daftar notifikasi sistem untuk pengguna yang login (notifikasi personal, notifikasi berdasarkan role, atau notifikasi global sistem) dengan filter status belum dibaca (unread) dan paginasi.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->visible($request);
        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }
        $paginator = $query->latest()->paginate($validated['per_page'] ?? 20);

        return ApiResponse::paginated($paginator, NotificationResource::collection($paginator->getCollection())->resolve());
    }

    /**
     * Jumlah Notifikasi Belum Dibaca
     *
     * Menghitung total pemberitahuan sistem yang belum dibaca (unread) oleh pengguna yang sedang login.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['count' => $this->visible($request)->where('is_read', false)->count()]);
    }

    /**
     * Tandai Notifikasi Telah Dibaca
     *
     * Mengubah status satu pesan notifikasi tertentu menjadi sudah dibaca (is_read = true).
     *
     * @param Request $request
     * @param Notification $notification
     * @return JsonResponse
     */
    public function read(Request $request, Notification $notification): JsonResponse
    {
        if (! $this->visible($request)->whereKey($notification->id)->exists()) {
            return ApiResponse::error('Notification not found.', 'RESOURCE_NOT_FOUND', 404);
        }
        $notification->update(['is_read' => true]);

        return ApiResponse::success((new NotificationResource($notification))->resolve(), 'Notification marked as read.');
    }

    /**
     * Tandai Semua Notifikasi Telah Dibaca
     *
     * Mengubah seluruh pesan notifikasi belum dibaca milik pengguna menjadi sudah dibaca sekaligus.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = $this->visible($request)->where('is_read', false)->update(['is_read' => true]);

        return ApiResponse::success(['updated' => $count], 'All notifications marked as read.');
    }

    private function visible(Request $request): Builder
    {
        $user = $request->user();

        return Notification::query()->where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere(fn ($role) => $role->whereNull('user_id')->where('role', $user->role))
                ->orWhere(fn ($global) => $global->whereNull('user_id')->whereNull('role'));
        });
    }
}
