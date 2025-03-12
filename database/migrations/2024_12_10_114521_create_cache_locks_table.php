<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCacheLocksTable extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('cache_locks', function (Blueprint $table) {
      $table->comment('Stores the locks of the cache.');
      $table->string('key')->primary();
      $table->string('owner');
      $table->integer('expiration');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('cache_locks');
  }
}
