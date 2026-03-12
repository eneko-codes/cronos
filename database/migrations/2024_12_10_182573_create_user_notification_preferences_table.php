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
        // Add muted_notifications column to users table
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'muted_notifications')) {
                $table->boolean('muted_notifications')->default(false)->after('remember_token');
            }
        });

        // Create user notification preferences table with key-value structure
        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('notification_type');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'notification_type']);

            // Performance indexes
            $table->index('notification_type', 'idx_user_notif_type');
            $table->index(['user_id', 'enabled'], 'idx_user_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'muted_notifications')) {
                $table->dropColumn('muted_notifications');
            }
        });
    }
};
