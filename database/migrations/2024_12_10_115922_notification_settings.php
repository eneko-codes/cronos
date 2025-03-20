<?php

use App\Models\NotificationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('notification_settings', function (Blueprint $table) {
      $table->id();
      $table->string('key')->unique();
      $table->boolean('enabled')->default(true);
      $table->timestamps();
    });

    NotificationSetting::updateOrCreate(
      ['key' => 'api_down_warning_mail'],
      ['enabled' => true]
    );

    NotificationSetting::updateOrCreate(
      ['key' => 'welcome_email'],
      ['enabled' => true]
    );

    NotificationSetting::updateOrCreate(
      ['key' => 'data_retention_enabled'],
      ['enabled' => false]
    );

    // Create data retention settings table
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

  public function down(): void
  {
    Schema::dropIfExists('data_retention_settings');
    Schema::dropIfExists('notification_settings');
  }
};
