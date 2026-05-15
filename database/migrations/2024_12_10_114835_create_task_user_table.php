<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskUserTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('proofhub_task_id');
            $table->timestamps();

            // Foreign Key Constraint
            $table
                ->foreign('proofhub_task_id')
                ->references('proofhub_task_id')
                ->on('tasks');

            // Unique Constraint to prevent duplicate entries
            $table->unique(['user_id', 'proofhub_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
}
