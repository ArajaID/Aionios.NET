<?php

use App\Models\User;

test('user role helpers return the correct access capabilities', function () {
    $owner = new User(['role' => 'owner']);
    $finance = new User(['role' => 'admin_keuangan']);
    $network = new User(['role' => 'admin_jaringan']);

    expect($owner->isOwner())->toBeTrue()
        ->and($owner->isFinance())->toBeTrue()
        ->and($owner->isNetwork())->toBeTrue()
        ->and($finance->isOwner())->toBeFalse()
        ->and($finance->isFinance())->toBeTrue()
        ->and($finance->isNetwork())->toBeFalse()
        ->and($network->isOwner())->toBeFalse()
        ->and($network->isFinance())->toBeFalse()
        ->and($network->isNetwork())->toBeTrue();
});
