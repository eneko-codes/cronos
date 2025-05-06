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
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            // Add the new column, defaulting to true (enabled) for existing users
            $table->boolean('admin_promotion_email')->default(true)->after('api_down_warning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn('admin_promotion_email');
        });
    }
};
