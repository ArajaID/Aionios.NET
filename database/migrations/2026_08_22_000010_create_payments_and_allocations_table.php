<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('payment_date');
            $table->enum('payment_method', ['manual', 'qris'])->default('manual');
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('mdr_percentage', 5, 2)->default(0);
            $table->decimal('mdr_fee', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('reversal_requests', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // payment, expense, other_income, capital, manual_journal
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_requests');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
