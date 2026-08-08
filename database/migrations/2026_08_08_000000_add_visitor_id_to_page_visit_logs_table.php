<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('page_visit_logs', 'visitor_id')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->string('visitor_id')->nullable()->after('session_id')->index()
                    ->comment('Anonymous visitor identifier from cookie, used to attribute visits after login/register');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('page_visit_logs', 'visitor_id')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->dropColumn('visitor_id');
            });
        }
    }
};
