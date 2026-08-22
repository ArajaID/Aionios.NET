<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->unsignedTinyInteger('timeout')->default(5)->after('password');
        });

        DB::table('mikrotik_routers')
            ->whereNotNull('password')
            ->orderBy('id')
            ->each(function (object $router): void {
                DB::table('mikrotik_routers')
                    ->where('id', $router->id)
                    ->update(['password' => Crypt::encryptString($router->password)]);
            });
    }

    public function down(): void
    {
        DB::table('mikrotik_routers')
            ->whereNotNull('password')
            ->orderBy('id')
            ->each(function (object $router): void {
                DB::table('mikrotik_routers')
                    ->where('id', $router->id)
                    ->update(['password' => Crypt::decryptString($router->password)]);
            });

        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->dropColumn('timeout');
        });
    }
};
