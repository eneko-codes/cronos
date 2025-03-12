<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLoginTokensTable extends Migration
{
  public function up(): void
  {
    Schema::create('login_tokens', function (Blueprint $table) {
      $table->comment('Stores the login tokens of the users.');
      $table->id();
      $table->foreignId('user_id')->constrained();
      $table->string('token')->unique();
      $table->timestamp('expires_at');
      $table->boolean('remember')->default(false);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('login_tokens');
  }
}
