<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCacheTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('cache', function (Blueprint $table) {
      $table->comment('Stores the cache of the application.');
      $table->string('key')->primary();
      $table->mediumText('value');
      $table->integer('expiration');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cache');
  }
}
