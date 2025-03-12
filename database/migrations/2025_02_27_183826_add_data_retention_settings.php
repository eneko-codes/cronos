<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    // Add the data retention settings to the notification_settings table
    DB::table('notification_settings')->insert([
      [
        'key' => 'data_retention_enabled',
        'enabled' => false,
        'created_at' => now(),
        'updated_at' => now(),
      ],
    ]);

    // Create a new table to store the retention period configuration
    Schema::create('data_retention_settings', function (Blueprint $table) {
      $table->id();
      $table->string('data_type'); // timeentries, attendances, schedules, leaves
      $table->integer('retention_days')->default(365); // Default to 1 year
      $table->timestamps();
    });

    // Insert default retention periods for each data type
    DB::table('data_retention_settings')->insert([
      [
        'data_type' => 'time_entries',
        'retention_days' => 365, // 1 year
        'created_at' => now(),
        'updated_at' => now(),
      ],
      [
        'data_type' => 'user_attendances',
        'retention_days' => 365, // 1 year
        'created_at' => now(),
        'updated_at' => now(),
      ],
      [
        'data_type' => 'user_schedules',
        'retention_days' => 365, // 1 year
        'created_at' => now(),
        'updated_at' => now(),
      ],
      [
        'data_type' => 'user_leaves',
        'retention_days' => 365, // 1 year
        'created_at' => now(),
        'updated_at' => now(),
      ],
    ]);
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    // Remove the data retention setting
    DB::table('notification_settings')
      ->where('key', 'data_retention_enabled')
      ->delete();

    // Drop the retention period settings table
    Schema::dropIfExists('data_retention_settings');
  }
};
