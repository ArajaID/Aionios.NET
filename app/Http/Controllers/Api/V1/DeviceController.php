<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceRequest;
use App\Models\MobileDevice;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Perangkat Mobile (Devices)
 */
class DeviceController extends Controller
{
    /**
     * Registrasi Token Perangkat Mobile
     *
     * Mendaftarkan atau memperbarui token push notification (FCM / APNs) perangkat mobile petugas teknis atau kasir untuk menerima notifikasi sistem.
     *
     * @param DeviceRequest $request
     * @return JsonResponse
     */
    public function store(DeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $device = MobileDevice::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $data['device_id']],
            [
                'platform' => $data['platform'],
                'push_token' => $data['push_token'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );
        AuditService::log('register_mobile_device', 'devices', 'MobileDevice', $device->id, null, [
            'device_id' => $device->device_id,
            'platform' => $device->platform,
        ]);

        return ApiResponse::success($this->payload($device), 'Device registered.', $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Perbarui Data Perangkat Mobile
     *
     * Memperbarui token push notification, versi aplikasi, atau waktu aktif terakhir perangkat mobile milik pengguna.
     *
     * @param DeviceRequest $request
     * @param MobileDevice $device
     * @return JsonResponse
     */
    public function update(DeviceRequest $request, MobileDevice $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            return ApiResponse::error('Device not found.', 'RESOURCE_NOT_FOUND', 404);
        }
        $data = $request->validated();
        $device->update([
            'device_id' => $data['device_id'],
            'platform' => $data['platform'],
            'push_token' => $data['push_token'],
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        return ApiResponse::success($this->payload($device), 'Device updated.');
    }

    /**
     * Hapus Registrasi Perangkat Mobile
     *
     * Menghapus token perangkat mobile pengguna saat logout dari aplikasi mobile agar tidak lagi dikirimkan push notification.
     *
     * @param Request $request
     * @param MobileDevice $device
     * @return JsonResponse
     */
    public function destroy(Request $request, MobileDevice $device): JsonResponse
    {
        if ($device->user_id !== $request->user()->id) {
            return ApiResponse::error('Device not found.', 'RESOURCE_NOT_FOUND', 404);
        }
        AuditService::log('unregister_mobile_device', 'devices', 'MobileDevice', $device->id, ['device_id' => $device->device_id]);
        $device->delete();

        return ApiResponse::success(null, 'Device unregistered.');
    }

    private function payload(MobileDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'last_seen_at' => $device->last_seen_at?->toISOString(),
        ];
    }
}
