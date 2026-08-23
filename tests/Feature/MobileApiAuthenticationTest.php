<?php

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('health endpoint exposes the standard envelope and request correlation id', function () {
    $this->getJson('/api/v1/health', ['X-Request-ID' => 'mobile-health-1'])
        ->assertOk()
        ->assertHeader('X-Request-ID', 'mobile-health-1')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson([
            'success' => true,
            'data' => ['status' => 'ok'],
        ]);
});

test('active user can login inspect current user and revoke the active token', function () {
    $user = User::factory()->create([
        'password' => Hash::make('mobile-secret'),
        'role' => 'admin_keuangan',
        'is_active' => true,
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'mobile-secret',
        'device_name' => 'Pixel 10 Pro',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.role', 'admin_keuangan');

    $token = $login->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonFragment(['payments.create']);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    $this->withToken($token)->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTH_UNAUTHORIZED');
});

test('invalid credentials are generic and repeated login attempts are throttled', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'is_active' => true,
    ]);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'Test Device',
        ])->assertUnauthorized()->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Test Device',
    ])->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
});

test('protected endpoint returns 401 and network admin is forbidden from finance writes', function () {
    $this->getJson('/api/v1/customers')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTH_UNAUTHORIZED');

    $network = User::factory()->create(['role' => 'admin_jaringan', 'is_active' => true]);
    Sanctum::actingAs($network, RolePermissions::forRole($network->role));

    $this->postJson('/api/v1/payments/preview', [])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
