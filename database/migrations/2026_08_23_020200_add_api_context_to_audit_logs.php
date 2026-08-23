<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_idempotency_keys', function (Blueprint $table) {
            $table->string('key', 100)->change();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'source')) {
                $table->string('source', 20)->default('WEB')->after('user_agent');
            }
            if (! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 100)->nullable()->after('source')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->dropColumn('request_id');
            }
            if (Schema::hasColumn('audit_logs', 'source')) {
                $table->dropColumn('source');
            }
        });

        Schema::table('api_idempotency_keys', function (Blueprint $table) {
            $table->uuid('key')->change();
        });
    }
};
