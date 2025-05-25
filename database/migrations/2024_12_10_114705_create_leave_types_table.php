<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveTypesTable extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->comment('Stores the leave types fetched from Odoo using odoo_leave_type_id as primary key.');
            $table->unsignedBigInteger('odoo_leave_type_id')->primary();
            $table->string('name');
            $table->string('validation_type')->nullable()->after('name')->comment('Odoo validation type (e.g., hr, manager, both)');
            $table->string('request_unit')->nullable()->after('validation_type')->comment('Odoo request unit (e.g., day, half_day, hour)');
            $table->boolean('limit')->default(false);
            $table->boolean('requires_allocation')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_unpaid')->default(false)->after('active')->comment('Whether the leave type is unpaid in Odoo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
}
