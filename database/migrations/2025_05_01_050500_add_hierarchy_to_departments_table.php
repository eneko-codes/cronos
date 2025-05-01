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
        Schema::table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('odoo_manager_employee_id')->nullable()->after('active')->comment('Odoo ID of the manager employee');
            $table->unsignedBigInteger('odoo_parent_department_id')->nullable()->after('odoo_manager_employee_id')->comment('Odoo ID of the parent department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn(['odoo_manager_employee_id', 'odoo_parent_department_id']);
        });
    }
};
