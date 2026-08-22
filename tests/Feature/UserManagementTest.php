<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only owner can open user management', function () {
    $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
    $finance = User::factory()->create(['role' => 'admin_keuangan', 'is_active' => true]);

    $this->actingAs($owner)->get('/users')->assertOk();
    $this->actingAs($finance)->get('/users')->assertForbidden();
});

test('owner can create a login user with a role', function () {
    $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

    $this->actingAs($owner)->post('/users', [
        'name' => 'Admin Jaringan',
        'email' => 'network@example.com',
        'phone' => '08123456789',
        'role' => 'admin_jaringan',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'network@example.com',
        'role' => 'admin_jaringan',
        'is_active' => true,
    ]);
});

test('owner cannot deactivate the account currently in use', function () {
    $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

    $this->actingAs($owner)->put("/users/{$owner->id}", [
        'name' => $owner->name,
        'email' => $owner->email,
        'phone' => $owner->phone,
        'role' => 'owner',
        'password' => '',
        'password_confirmation' => '',
        'is_active' => false,
    ])->assertSessionHasErrors('role');

    expect($owner->fresh()->is_active)->toBeTrue();
});

test('owner can soft delete another user while keeping historical references safe', function () {
    $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
    $target = User::factory()->create(['role' => 'admin_keuangan', 'is_active' => true]);

    $this->actingAs($owner)->delete("/users/{$target->id}")->assertSessionHasNoErrors();

    $this->assertSoftDeleted('users', ['id' => $target->id]);
});
