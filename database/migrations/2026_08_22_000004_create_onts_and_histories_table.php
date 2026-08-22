<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onts', function (Blueprint $table) {
            $table->id();
            $table->string('ont_id')->unique();
            $table->string('brand');
            $table->string('model');
            $table->string('serial_number')->unique();
            $table->string('mac_address')->nullable();
            $table->enum('status', ['available', 'installed', 'returned', 'damaged', 'lost'])->default('available');
            $table->string('condition')->default('good');
            $table->unsignedBigInteger('current_customer_id')->nullable();
            $table->date('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ont_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ont_id')->constrained('onts')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('action'); // assigned, returned, replaced, status_changed
            $table->string('condition')->default('good');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ont_histories');
        Schema::dropIfExists('onts');
    }
};
