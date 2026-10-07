<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * On large tables, adding indexes may take a while; run during a maintenance window if needed.
     */
    public function up(): void
    {
        Schema::table('page_visit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('page_visit_logs', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('ip_info')
                    ->comment('ISO country code extracted from ip_info for efficient grouping');
            }

            if (! Schema::hasColumn('page_visit_logs', 'path')) {
                $table->string('path', 255)->nullable()->after('page_url')
                    ->comment('Normalized request path (no query string) for aggregation');
            }
        });

        Schema::table('page_visit_logs', function (Blueprint $table) {
            $table->index('session_id');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index(['is_bot', 'created_at']);
            $table->index('country_code');
            $table->index('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_visit_logs', function (Blueprint $table) {
            $table->dropIndex(['session_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_bot', 'created_at']);
            $table->dropIndex(['country_code']);
            $table->dropIndex(['path']);

            $columns = [];
            if (Schema::hasColumn('page_visit_logs', 'country_code')) {
                $columns[] = 'country_code';
            }
            if (Schema::hasColumn('page_visit_logs', 'path')) {
                $columns[] = 'path';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
