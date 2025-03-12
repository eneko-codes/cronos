<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('departments', function (Blueprint $table) {
      $table->comment('Stores the departments from Odoo with odoo_department_id as primary key.');
      $table->unsignedBigInteger('odoo_department_id')->primary();
      $table->string('name');
      $table->boolean('active')->default(true);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('departments');
  }
}
