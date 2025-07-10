<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('proofhub_task_id')->primary(); // ProofHub task ID
            $table->unsignedBigInteger('proofhub_project_id');
            $table->foreign('proofhub_project_id')
                ->references('proofhub_project_id')
                ->on('projects');
            $table->string('name');
            $table->string('status')->nullable()->comment('Task status from ProofHub');
            $table->date('due_date')->nullable()->comment('Task due date from ProofHub');
            $table->text('description')->nullable()->comment('Task description from ProofHub');
            $table->json('tags')->nullable()->comment('Tags from ProofHub, stored as JSON array');
            $table->string('priority')->nullable()->comment('Task priority from ProofHub');
            $table->timestamp('proofhub_created_at')->nullable()->comment('Task creation time in ProofHub');
            $table->timestamp('proofhub_updated_at')->nullable()->comment('Task last update time in ProofHub');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
}
