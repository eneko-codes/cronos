<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryUserTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up(): void
  {
    Schema::create('category_user', function (Blueprint $table) {
      $table->comment(
        'Pivotal table between users and categories (odoo_category_id).'
      );
      $table->foreignId('user_id')->constrained();
      $table->unsignedBigInteger('category_id'); // Changed from odoo_category_id
      $table->timestamps();

      $table
        ->foreign('category_id')
        ->references('odoo_category_id')
        ->on('categories');
      $table->primary(['user_id', 'category_id']);
    });
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down(): void
  {
    Schema::dropIfExists('category_user');
  }
}
