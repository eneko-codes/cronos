<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enables PostgreSQL unaccent extension for accent-insensitive search.
     * This allows searching "formacion" to match "Formación" at the database level.
     */
    public function up(): void
    {
        // Only enable for PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
            } catch (\Exception $e) {
                // Extension might already exist or require superuser privileges
                // Log but don't fail migration
                \Log::warning('Could not enable unaccent extension: '.$e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: We don't drop the extension as it might be used elsewhere.
     * If you need to drop it, do so manually: DROP EXTENSION IF EXISTS unaccent;
     */
    public function down(): void
    {
        // Intentionally left empty - don't drop extension as it might be used elsewhere
    }
};
