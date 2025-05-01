<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('department_id');
            $table->string('odoo_manager_id')->nullable()->after('job_title');

            // Optional: Add a foreign key constraint if desired, though it might complicate seeding/syncing
            // Ensure the type matches the type of odoo_id if you add this.
            // $table->foreign('odoo_manager_id')->references('odoo_id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key first if it was added
            // $table->dropForeign(['odoo_manager_id']);
            $table->dropColumn(['job_title', 'odoo_manager_id']);
        });
    }
};
