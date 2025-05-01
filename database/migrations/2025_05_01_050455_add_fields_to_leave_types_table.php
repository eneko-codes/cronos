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
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('validation_type')->nullable()->after('name')->comment('Odoo validation type (e.g., hr, manager, both)');
            $table->string('request_unit')->nullable()->after('validation_type')->comment('Odoo request unit (e.g., day, half_day, hour)');
            $table->boolean('is_unpaid')->default(false)->after('active')->comment('Whether the leave type is unpaid in Odoo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['validation_type', 'request_unit', 'is_unpaid']);
        });
    }
};
