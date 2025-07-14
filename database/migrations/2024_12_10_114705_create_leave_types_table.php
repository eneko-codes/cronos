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
            $table->string('request_unit')->nullable()->comment('Odoo request unit (e.g., day, half_day, hour)');
            $table->boolean('active')->default(true);
            $table->string('odoo_created_at')->nullable();
            $table->string('odoo_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
}
