<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectUserTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('project_user', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained();
      $table->unsignedBigInteger('proofhub_project_id');
      $table->foreign('proofhub_project_id')->references('proofhub_project_id')->on('projects');
      $table->timestamps();

      $table->unique(['user_id', 'proofhub_project_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('project_user');
  }
}
