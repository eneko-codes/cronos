<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Using Odoo's 'id' field as 'odoo_schedule_id' in our local DB.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->comment(
                'Stores the schedules fetched from Odoo using the Odoo id as odoo_schedule_id'
            );
            $table->unsignedBigInteger('odoo_schedule_id')->primary();
            $table->string('description')->nullable();
            $table->float('average_hours_day')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
}
