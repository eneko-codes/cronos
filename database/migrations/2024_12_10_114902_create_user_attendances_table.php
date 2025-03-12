<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAttendancesTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('user_attendances', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained('users');
      $table->date('date');
      $table->integer('presence_seconds'); // Changed from presence_minutes float to presence_seconds integer
      $table->timestamp('start')->nullable(); // UTC timestamp
      $table->timestamp('end')->nullable(); // UTC timestamp
      $table->boolean('is_remote');
      $table->timestamps();

      $table->index('user_id');
      $table->index(['user_id', 'date']);
      $table->index(['start', 'end']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('user_attendances');
  }
}
