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
        Schema::create('schedules', function (Blueprint $table): void {
            $table->comment(
                'Stores the schedules fetched from Odoo using the Odoo id as odoo_schedule_id'
            );
            $table->unsignedBigInteger('odoo_schedule_id')->primary();
            $table->string('description')->nullable();
            $table->float('average_hours_day')->nullable();
            $table->boolean('two_weeks_calendar')->default(false)->comment('Indicates if the calendar has a bi-weekly rotation (Week 1 vs Week 2).');
            $table->string('two_weeks_explanation')->nullable()->comment('Human-readable explanation of the two-week rotation.');
            $table->boolean('flexible_hours')->default(false)->comment('Whether the calendar allows flexible start/end times.');
            $table->boolean('active')->default(true)->comment('Whether the schedule is active.');
            $table->timestamp('odoo_created_at')->nullable()->comment('Creation date of the record in Odoo (UTC).');
            $table->timestamp('odoo_updated_at')->nullable()->comment('Last update date of the record in Odoo (UTC).');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
}
