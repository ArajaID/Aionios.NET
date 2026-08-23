<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'isolated', 'terminated'])
                ->default('pending')
                ->change();
        });

        if (! Schema::hasIndex('payment_allocations', 'payment_allocations_invoice_id_unique')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->unique('invoice_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('payment_allocations', 'payment_allocations_invoice_id_unique')) {
            Schema::table('payment_allocations', function (Blueprint $table) {
                $table->dropUnique(['invoice_id']);
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->enum('status', ['active', 'isolated', 'terminated'])
                ->default('active')
                ->change();
        });
    }
};
