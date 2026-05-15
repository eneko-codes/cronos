<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_notification_preferences', function (Blueprint $table): void {
            $table->string('notification_type')->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // Performance index for filtering enabled/disabled preferences
            $table->index('enabled', 'idx_global_notif_enabled');
        });

        // Create essential global notification preferences (not seeding - this is core app data)
        $this->createGlobalNotificationPreferences();
    }

    public function down(): void
    {
        Schema::dropIfExists('global_notification_preferences');
    }

    private function createGlobalNotificationPreferences(): void
    {
        // Create global master switch
        DB::table('global_notification_preferences')->insert([
            'notification_type' => 'global_master',
            'enabled' => true, // Default to enabled
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create preferences for each notification type using the enum
        foreach (NotificationType::cases() as $type) {
            DB::table('global_notification_preferences')->insert([
                'notification_type' => $type->value,
                'enabled' => $type->defaultEnabled(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
