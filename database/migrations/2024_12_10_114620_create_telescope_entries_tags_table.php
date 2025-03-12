<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTelescopeEntriesTagsTable extends Migration
{
  /**
   * Get the migration connection name.
   */
  public function getConnection(): ?string
  {
    return config('telescope.storage.database.connection');
  }

  /**
   * Run the migrations.
   */
  public function up(): void
  {
    $schema = Schema::connection($this->getConnection());

    $schema->create('telescope_entries_tags', function (Blueprint $table) {
      $table->comment(
        'Stores the tags of the Laravel Telescope monitoring tool entries.'
      );
      $table->uuid('entry_uuid');
      $table->string('tag');

      $table->primary(['entry_uuid', 'tag']);
      $table->index('tag');

      $table
        ->foreign('entry_uuid')
        ->references('uuid')
        ->on('telescope_entries')
        ->onDelete('cascade');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    $schema = Schema::connection($this->getConnection());

    $schema->dropIfExists('telescope_entries_tags');
  }
}
