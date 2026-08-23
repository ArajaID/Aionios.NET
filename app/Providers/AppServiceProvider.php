<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('api-read', fn (Request $request) => Limit::perMinute(120)->by((string) ($request->user()?->id ?? $request->ip()))
        );

        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip()))
        );

        Gate::define('viewApiDocs', fn (?User $user) => $user?->isOwner() === true);
    }
}
