<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTimeEntriesTable extends Migration
{
  /**
   * Run the migrations.
   *
   * Creates the time_entries table with fields aligned to ProofHub's data structure.
   *
   * @return void
   */
  public function up(): void
  {
    Schema::create('time_entries', function (Blueprint $table) {
      $table
        ->unsignedBigInteger('proofhub_time_entry_id')
        ->primary()
        ->comment('ProofHub time entry ID');
      $table->foreignId('user_id')->constrained();
      $table->unsignedBigInteger('proofhub_project_id');
      $table
        ->foreign('proofhub_project_id')
        ->references('proofhub_project_id')
        ->on('projects');
      $table->unsignedBigInteger('proofhub_task_id')->nullable();
      $table
        ->foreign('proofhub_task_id')
        ->references('proofhub_task_id')
        ->on('tasks');
      $table
        ->string('status')
        ->default('none')
        ->comment('Status of the time entry');
      $table
        ->text('description')
        ->nullable()
        ->comment('Description of the time entry');
      $table->date('date')->comment('Date of the time entry in UTC');
      $table
        ->unsignedInteger('duration_seconds')
        ->default(0)
        ->comment('Duration in seconds');
      $table
        ->timestamp('proofhub_created_at')
        ->nullable()
        ->comment('Original creation time in ProofHub');
      $table->timestamps();

      // Unique constraint to prevent duplicate time entries per user, project, task, and date
      $table->unique(
        ['user_id', 'proofhub_project_id', 'proofhub_task_id', 'date'],
        'unique_time_entry'
      );
    });
  }

  /**
   * Reverse the migrations.
   *
   * Drops the time_entries table.
   *
   * @return void
   */
  public function down(): void
  {
    Schema::dropIfExists('time_entries');
  }
}
