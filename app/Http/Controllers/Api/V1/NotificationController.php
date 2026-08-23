<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
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

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['count' => $this->visible($request)->where('is_read', false)->count()]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        if (! $this->visible($request)->whereKey($notification->id)->exists()) {
            return ApiResponse::error('Notification not found.', 'RESOURCE_NOT_FOUND', 404);
        }
        $notification->update(['is_read' => true]);

        return ApiResponse::success((new NotificationResource($notification))->resolve(), 'Notification marked as read.');
    }

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
