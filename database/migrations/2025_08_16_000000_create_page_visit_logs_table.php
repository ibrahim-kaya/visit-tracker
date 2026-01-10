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
        Schema::create('page_visit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable()->comment('The ID of the user who visited the page, if logged in');
            $table->string('session_id')->nullable()->comment('The session ID of the visitor, if available');
            $table->string('ip_address')->nullable()->comment('The IP address of the visitor');
            $table->text('referrer')->nullable()->comment('The referrer URL, if available');
            $table->string('device_type')->nullable()->comment('The type of device used to visit the page (e.g., desktop, mobile, tablet)');
            $table->string('browser')->nullable()->comment('The browser used to visit the page');
            $table->string('platform')->nullable()->comment('The platform or operating system of the device used to visit the page');
            $table->text('ip_info')->nullable()->comment('Additional information about the IP address, such as location data, stored as JSON');
            $table->text('page_url')->nullable()->comment('The URL of the visited page');
            $table->text('user_agent')->nullable()->comment('The user agent of the visitor');
            $table->boolean('is_bot')->default(false)->comment('Indicates whether the visitor is a bot or not');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_visit_logs');
    }
};
