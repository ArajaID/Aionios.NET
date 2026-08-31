<?php

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\PppAccount;
use App\Models\User;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->networkAdmin = User::where('role', 'admin_jaringan')->firstOrFail();
    $this->financeAdmin = User::where('role', 'admin_keuangan')->firstOrFail();
    $this->owner = User::where('role', 'owner')->firstOrFail();
});

test('unauthenticated request to pppoe-sessions returns 401 AUTH_UNAUTHORIZED', function () {
    $response = $this->getJson('/api/v1/network/pppoe-sessions');

    $response->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'message' => 'Authentication is required.',
            'error' => [
                'code' => 'AUTH_UNAUTHORIZED',
            ],
        ]);
});

test('user without network.view permission receives 403 FORBIDDEN', function () {
    Sanctum::actingAs($this->financeAdmin, RolePermissions::forRole($this->financeAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions');

    $response->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'You do not have permission to perform this action.',
            'error' => [
                'code' => 'FORBIDDEN',
            ],
        ]);
});

test('authenticated user with network.view permission can access pppoe-sessions', function () {
    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik', 'version' => '7.24'], 200),
        '*/rest/ppp/active' => Http::response([
            [
                'name' => 'pppoe-test-1',
                'address' => '10.10.10.25',
                'uptime' => '1h23m45s',
                'caller-id' => 'AA:BB:CC:DD:EE:FF',
                'service' => 'pppoe',
            ],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'customer_id',
                    'customer_code',
                    'customer_name',
                    'username',
                    'profile',
                    'is_isolated',
                    'is_online',
                    'session',
                ],
            ],
            'meta' => [
                'router_status',
                'total_accounts',
                'online',
                'offline',
                'checked_at',
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
});

test('online pppoe session matches username and provides ip and uptime while offline accounts have session null', function () {
    $activeAccount = PppAccount::with('customer')->firstOrFail();
    $offlineAccount = PppAccount::where('id', '!=', $activeAccount->id)->firstOrFail();

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik', 'version' => '7.24'], 200),
        '*/rest/ppp/active' => Http::response([
            [
                'name' => $activeAccount->username,
                'address' => '10.10.10.88',
                'uptime' => '3h42m10s',
                'caller-id' => '00:11:22:33:44:55',
                'service' => 'pppoe',
                'session-id' => '81000001',
            ],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions?per_page=100');

    $response->assertOk();
    $data = collect($response->json('data'));

    $matchedOnline = $data->firstWhere('username', $activeAccount->username);
    expect($matchedOnline)->not->toBeNull()
        ->and($matchedOnline['is_online'])->toBeTrue()
        ->and($matchedOnline['session'])->not->toBeNull()
        ->and($matchedOnline['session']['address'])->toBe('10.10.10.88')
        ->and($matchedOnline['session']['uptime'])->toBe('3h42m10s')
        ->and($matchedOnline['session']['caller_id'])->toBe('00:11:22:33:44:55')
        ->and($matchedOnline['session']['service'])->toBe('pppoe')
        ->and($matchedOnline['customer_id'])->toBe($activeAccount->customer->id);

    $matchedOffline = $data->firstWhere('username', $offlineAccount->username);
    expect($matchedOffline)->not->toBeNull()
        ->and($matchedOffline['is_online'])->toBeFalse()
        ->and($matchedOffline['session'])->toBeNull();

    $meta = $response->json('meta');
    expect($meta['router_status'])->toBe('online')
        ->and($meta['online'])->toBe(1)
        ->and($meta['offline'])->toBe($meta['total_accounts'] - 1);
});

test('never exposes pppoe passwords or mikrotik router credentials in json response', function () {
    $router = MikrotikRouter::firstOrFail();
    $ppp = PppAccount::firstOrFail();

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([
            ['name' => $ppp->username, 'address' => '10.10.10.10', 'uptime' => '10m'],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions');
    $response->assertOk();

    $rawJson = $response->getContent();

    expect($rawJson)->not->toContain($router->password)
        ->not->toContain($router->username)
        ->not->toContain($router->host)
        ->not->toContain($ppp->password);
});

test('filters by status online and offline correctly', function () {
    $activeAccount = PppAccount::firstOrFail();

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([
            ['name' => $activeAccount->username, 'address' => '10.10.10.12', 'uptime' => '5m'],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    // Filter Online
    $resOnline = $this->getJson('/api/v1/network/pppoe-sessions?status=online');
    $resOnline->assertOk();
    $dataOnline = $resOnline->json('data');
    expect($dataOnline)->toHaveCount(1)
        ->and($dataOnline[0]['username'])->toBe($activeAccount->username)
        ->and($dataOnline[0]['is_online'])->toBeTrue();

    // Filter Offline
    $resOffline = $this->getJson('/api/v1/network/pppoe-sessions?status=offline');
    $resOffline->assertOk();
    $dataOffline = collect($resOffline->json('data'));
    expect($dataOffline->every(fn ($item) => $item['is_online'] === false))->toBeTrue()
        ->and($dataOffline->contains('username', $activeAccount->username))->toBeFalse();
});

test('search works across customer name, customer code, and pppoe username', function () {
    $target = PppAccount::with('customer')->firstOrFail();

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    // Search by Customer Name
    $byName = $this->getJson('/api/v1/network/pppoe-sessions?search=' . urlencode(substr($target->customer->name, 0, 4)));
    $byName->assertOk();
    expect(collect($byName->json('data'))->pluck('username'))->toContain($target->username);

    // Search by Customer Code
    $byCode = $this->getJson('/api/v1/network/pppoe-sessions?search=' . urlencode($target->customer->customer_id));
    $byCode->assertOk();
    expect(collect($byCode->json('data'))->pluck('username'))->toContain($target->username);

    // Search by Username
    $byUser = $this->getJson('/api/v1/network/pppoe-sessions?search=' . urlencode($target->username));
    $byUser->assertOk();
    expect(collect($byUser->json('data'))->pluck('username'))->toContain($target->username);
});

test('pagination functions correctly and validates max per_page', function () {
    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $page1 = $this->getJson('/api/v1/network/pppoe-sessions?per_page=2&page=1');
    $page1->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 1);
    expect($page1->json('data'))->toHaveCount(2);

    $page2 = $this->getJson('/api/v1/network/pppoe-sessions?per_page=2&page=2');
    $page2->assertOk()
        ->assertJsonPath('meta.current_page', 2);
    expect($page2->json('data'))->toHaveCount(2);

    // Reject per_page > 100 with validation error 422
    $invalid = $this->getJson('/api/v1/network/pppoe-sessions?per_page=101');
    $invalid->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['per_page']]]);
});

test('isolated profile or isolated customer state reflects is_isolated true', function () {
    $isolatedCustomer = Customer::where('status', 'isolated')->whereHas('pppAccount')->firstOrFail();
    $isolatedPpp = $isolatedCustomer->pppAccount;

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([
            ['name' => $isolatedPpp->username, 'address' => '10.10.10.99', 'uptime' => '10m'],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions?search=' . urlencode($isolatedPpp->username));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('username', $isolatedPpp->username);
    expect($item)->not->toBeNull()
        ->and($item['is_isolated'])->toBeTrue()
        ->and($item['is_online'])->toBeTrue(); // Online and isolated are independent states
});

test('router offline is gracefully handled returning 200 OK without raw exception', function () {
    // Simulate RouterOS offline / exception
    Http::fake([
        '*/rest/system/resource' => Http::response(['error' => 'Connection refused'], 500),
        '*/rest/ppp/active' => Http::response(['error' => 'Connection refused'], 500),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.router_status', 'offline')
        ->assertJsonPath('meta.online', 0);

    $rawContent = $response->getContent();
    expect($rawContent)->not->toContain('Exception')
        ->not->toContain('Connection refused');
});

test('pppoe sessions query does not introduce N+1 query problem', function () {
    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/v1/network/pppoe-sessions?per_page=100')->assertOk();

    $queries = DB::getQueryLog();
    // Expected: token query, user query, router query (if active), ppp_accounts with customers eager loaded
    // Should definitely be well under 10 queries, NOT scaling with number of accounts
    expect(count($queries))->toBeLessThan(10);
});

test('lightweight pppoe session summary endpoint returns correct aggregate data', function () {
    $activeAccount = PppAccount::firstOrFail();

    Http::fake([
        '*/rest/system/resource' => Http::response(['platform' => 'MikroTik'], 200),
        '*/rest/ppp/active' => Http::response([
            ['name' => $activeAccount->username, 'address' => '10.10.10.1', 'uptime' => '20m'],
        ], 200),
    ]);

    Sanctum::actingAs($this->networkAdmin, RolePermissions::forRole($this->networkAdmin->role));

    $response = $this->getJson('/api/v1/network/pppoe-sessions/summary');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'router_status',
                'total_accounts',
                'online',
                'offline',
                'checked_at',
            ],
        ])
        ->assertJsonPath('data.router_status', 'online')
        ->assertJsonPath('data.online', 1);

    expect($response->json('data.offline'))->toBe($response->json('data.total_accounts') - 1);
});
