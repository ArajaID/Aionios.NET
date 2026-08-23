<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->unique();
            $table->string('name');
            $table->string('phone');
            $table->text('address');
            $table->date('installed_at')->nullable();
            $table->date('activated_at')->nullable();
            $table->foreignId('package_id')->constrained('packages');
            $table->foreignId('ont_id')->nullable()->constrained('onts')->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'isolated', 'terminated'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('old_status');
            $table->string('new_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('original_ppp_profile')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_promotions');
        Schema::dropIfExists('customer_status_histories');
        Schema::dropIfExists('customers');
    }
};
