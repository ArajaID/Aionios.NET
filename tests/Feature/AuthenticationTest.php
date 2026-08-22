<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guest can open login page', function () {
    $this->get('/login')->assertOk();
});

test('active user can login and logout', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret-password'),
        'role' => 'owner',
        'is_active' => true,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret-password',
        'remember' => true,
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('invalid credentials cannot login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'role' => 'owner',
        'is_active' => true,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive user cannot login with valid credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'role' => 'admin_keuangan',
        'is_active' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
