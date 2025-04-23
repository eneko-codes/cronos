<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveTypesTable extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->comment('Stores the leave types fetched from Odoo using odoo_leave_type_id as primary key.');
            $table->unsignedBigInteger('odoo_leave_type_id')->primary();
            $table->string('name');
            $table->boolean('limit')->default(false);
            $table->boolean('requires_allocation')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
}
