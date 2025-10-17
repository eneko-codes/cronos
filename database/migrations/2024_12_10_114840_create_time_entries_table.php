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
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
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
            $table->unsignedBigInteger('proofhub_task_id')->nullable()->comment('ProofHub task ID');
            $table
                ->string('status')
                ->default('none')
                ->comment('Status of the time entry');
            $table
                ->text('description')
                ->nullable()
                ->comment('Description of the time entry');
            $table->date('date')->comment('Date of the time entry');
            $table
                ->unsignedInteger('duration_seconds')
                ->default(0)
                ->comment('Duration in seconds');
            $table
                ->timestampTz('proofhub_created_at')
                ->nullable()
                ->comment('Original creation time in ProofHub (stored as UTC)');
            $table->timestampTz('proofhub_updated_at')->nullable()->comment('Last update time in ProofHub (stored as UTC)');
            $table->boolean('billable')->nullable()->comment('Whether the time entry is billable');
            $table->text('comments')->nullable()->comment('Comments from ProofHub');
            $table->json('tags')->nullable()->comment('Tags from ProofHub, stored as JSON array');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Drops the time_entries table.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
}
