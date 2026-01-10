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
        if (!Schema::hasColumn('page_visit_logs', 'method')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->string('method')->nullable()->after('user_agent')
                    ->comment('The HTTP method used for the request (e.g., GET, POST, PUT, DELETE)');
            });
        }
        
        if (!Schema::hasColumn('page_visit_logs', 'payload')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->text('payload')->nullable()->after('method')
                    ->comment('The request payload/body data, stored as JSON');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('page_visit_logs', 'method')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->dropColumn('method');
            });
        }
        
        if (Schema::hasColumn('page_visit_logs', 'payload')) {
            Schema::table('page_visit_logs', function (Blueprint $table) {
                $table->dropColumn('payload');
            });
        }
    }
};
