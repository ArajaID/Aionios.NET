<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_routers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Main Gateway');
            $table->string('host')->default('127.0.0.1');
            $table->integer('port')->default(8728);
            $table->string('username')->default('admin');
            $table->text('password')->nullable();
            $table->string('api_type')->default('rest'); // rest, api, api_ssl
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['online', 'offline', 'unknown'])->default('unknown');
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ppp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile')->default('default');
            $table->string('caller_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['connected', 'disconnected', 'isolated', 'disabled'])->default('disconnected');
            $table->string('current_ip')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('network_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('command'); // create_secret, change_profile, isolate, unisolate, terminate, reactivate, sync
            $table->string('target_type')->nullable(); // customer, ppp_account, router
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('network_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->foreignId('router_id')->nullable()->constrained('mikrotik_routers')->nullOnDelete();
            $table->string('ppp_username')->nullable();
            $table->string('status')->default('success');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_logs');
        Schema::dropIfExists('network_jobs');
        Schema::dropIfExists('ppp_accounts');
        Schema::dropIfExists('mikrotik_routers');
    }
};
