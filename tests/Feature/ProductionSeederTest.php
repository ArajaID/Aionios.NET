<?php

use App\Models\ApplicationSetting;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('production seeder creates one owner account and application settings', function () {
    $this->seed(ProductionSeeder::class);

    $owner = User::sole();

    expect($owner->name)->toBe('Abdul Rahman Jamil')
        ->and($owner->email)->toBe('jamil@aionios.net')
        ->and($owner->role)->toBe('owner')
        ->and($owner->is_active)->toBeTrue()
        ->and(Hash::check('aioniosisthenest', $owner->password))->toBeTrue()
        ->and(ApplicationSetting::where('key', 'app_brand_name')->value('value'))->toBe('Aionios.NET')
        ->and(ApplicationSetting::where('key', 'auto_isolate_time')->value('value'))->toBe('01:00')
        ->and(ApplicationSetting::count())->toBe(10);
});

test('production seeder can be run repeatedly without duplicate records', function () {
    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(User::count())->toBe(1)
        ->and(ApplicationSetting::count())->toBe(10);
});
