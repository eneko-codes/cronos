<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->comment('Stores the projects fetched from ProofHub');
            $table->unsignedBigInteger('proofhub_project_id')->primary(); // ProofHub project ID
            $table->string('name');
            $table->json('status')->nullable()->comment('Project status (object/array from ProofHub API)');
            $table->text('description')->nullable()->comment('Project description from ProofHub');
            $table->timestamp('proofhub_created_at')->nullable()->comment('Project creation time in ProofHub');
            $table->timestamp('proofhub_updated_at')->nullable()->comment('Project last update time in ProofHub');
            $table->unsignedBigInteger('proofhub_owner_id')->nullable()->comment('ProofHub user ID of the project owner');
            $table->foreign('proofhub_owner_id')->references('proofhub_id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
}
