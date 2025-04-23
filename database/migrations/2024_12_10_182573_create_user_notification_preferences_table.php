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
        Schema::create('user_notification_preferences', function (
            Blueprint $table
        ) {
            $table->id();
            $table
                ->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade')
                ->unique();
            $table->boolean('mute_all')->default(false);
            $table->boolean('schedule_change')->default(true);
            $table->boolean('weekly_user_report')->default(true);
            $table->boolean('leave_reminder')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'muted_notifications')) {
                $table->dropColumn('muted_notifications');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'muted_notifications')) {
                $table
                    ->boolean('muted_notifications')
                    ->default(false)
                    ->after('remember_token');
            }
        });

        Schema::dropIfExists('user_notification_preferences');
    }
};
