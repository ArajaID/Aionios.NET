<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->date('date');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('other_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('income_number')->unique();
            $table->date('date');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('capital_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->date('date');
            $table->enum('type', ['deposit', 'additional', 'drawing']);
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');
            $table->foreignId('cash_bank_account_id')->constrained('cash_bank_accounts');
            $table->decimal('amount', 14, 2);
            $table->string('description');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_transactions');
        Schema::dropIfExists('other_incomes');
        Schema::dropIfExists('expenses');
    }
};
