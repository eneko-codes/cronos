<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserLeavesTable extends Migration
{
  public function up(): void
  {
    Schema::create('user_leaves', function (Blueprint $table) {
      $table->comment(
        'Stores user leaves fetched from Odoo, using odoo_leave_id as unique identifier.'
      );
      $table->id();
      $table->string('odoo_leave_id')->unique();
      $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
      $table->datetime('start_date');
      $table->datetime('end_date');
      $table->enum('type', ['employee', 'department', 'category']);
      $table->string('status');
      $table->float('duration_days')->nullable();

      // For departments:
      $table->unsignedBigInteger('department_id')->nullable();
      $table
        ->foreign('department_id')
        ->references('odoo_department_id')
        ->on('departments');

      // For categories:
      $table->unsignedBigInteger('category_id')->nullable();
      $table
        ->foreign('category_id')
        ->references('odoo_category_id')
        ->on('categories');

      // For leave types:
      $table->unsignedBigInteger('leave_type_id')->nullable();
      $table
        ->foreign('leave_type_id')
        ->references('odoo_leave_type_id')
        ->on('leave_types');

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('user_leaves');
  }
}
