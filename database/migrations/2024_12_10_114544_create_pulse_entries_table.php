<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePulseEntriesTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('pulse_entries', function (Blueprint $table) {
      $table->comment(
        'Stores the entries of the Laravel Pulse monitoring tool.'
      );
      $table->id();
      $table->unsignedInteger('timestamp');
      $table->string('type');
      $table->mediumText('key');
      $table->string('key_hash');
      $table->bigInteger('value')->nullable();

      $table->index('timestamp');
      $table->index('type');
      $table->index('key_hash');
      $table->index(['timestamp', 'type', 'key_hash', 'value']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('pulse_entries');
  }
}
