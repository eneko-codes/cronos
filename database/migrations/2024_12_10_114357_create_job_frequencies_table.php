<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobFrequenciesTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('job_frequencies', function (Blueprint $table) {
      $table->comment('Stores the frequency of execution for the sync batch');
      $table->id();
      $table->string('frequency')->default('everyFiveMinutes');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('job_frequencies');
  }
}
