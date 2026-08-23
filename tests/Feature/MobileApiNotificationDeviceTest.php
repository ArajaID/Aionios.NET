<?php

use App\Models\MobileDevice;
use App\Models\Notification;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('notification access is scoped to the authenticated user and role', function () {
    $finance = User::factory()->create(['role' => 'admin_keuangan', 'is_active' => true]);
    $network = User::factory()->create(['role' => 'admin_jaringan', 'is_active' => true]);
    $visible = Notification::create(['role' => 'admin_keuangan', 'title' => 'Finance', 'message' => 'Visible']);
    $hidden = Notification::create(['user_id' => $network->id, 'title' => 'Network', 'message' => 'Hidden']);
    Sanctum::actingAs($finance, RolePermissions::forRole($finance->role));

    $this->getJson('/api/v1/notifications')->assertOk()
        ->assertJsonFragment(['title' => 'Finance'])
        ->assertJsonMissing(['title' => 'Network']);
    $this->postJson("/api/v1/notifications/{$hidden->id}/read")
        ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    $this->postJson("/api/v1/notifications/{$visible->id}/read")
        ->assertOk()->assertJsonPath('data.is_read', true);
});

test('push token is encrypted at rest and never returned by device endpoints', function () {
    $user = User::factory()->create(['role' => 'admin_keuangan', 'is_active' => true]);
    Sanctum::actingAs($user, RolePermissions::forRole($user->role));
    $plainToken = 'fcm-secret-device-token-123456';

    $response = $this->postJson('/api/v1/devices', [
        'device_id' => 'pixel-device-001',
        'platform' => 'android',
        'push_token' => $plainToken,
        'app_version' => '1.0.0',
    ])->assertCreated()->assertJsonMissing(['push_token' => $plainToken]);

    $deviceId = $response->json('data.id');
    $raw = DB::table('mobile_devices')->where('id', $deviceId)->value('push_token');
    expect($raw)->not->toBe($plainToken)
        ->and(MobileDevice::findOrFail($deviceId)->push_token)->toBe($plainToken);

    $this->deleteJson("/api/v1/devices/{$deviceId}")->assertOk();
    $this->assertDatabaseMissing('mobile_devices', ['id' => $deviceId]);
});
