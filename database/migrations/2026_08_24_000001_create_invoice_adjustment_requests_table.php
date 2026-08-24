<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->decimal('old_subtotal', 14, 2);
            $table->decimal('new_subtotal', 14, 2);
            $table->decimal('old_discount_amount', 14, 2)->default(0);
            $table->decimal('new_discount_amount', 14, 2)->default(0);
            $table->decimal('old_total_amount', 14, 2);
            $table->decimal('new_total_amount', 14, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_adjustment_requests');
    }
};
