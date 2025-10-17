<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduleDetailsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_details', function (Blueprint $table): void {
            $table->comment('Stores details of each schedule fetched from Odoo, linked by odoo_schedule_id and odoo_detail_id.');
            $table->id();
            $table->unsignedBigInteger('odoo_schedule_id');
            $table->unsignedBigInteger('odoo_detail_id');
            $table->string('name')->nullable()->comment('Name or label for the schedule detail.');
            $table->tinyInteger('weekday')->comment('Day of the week (0=Sunday, 6=Saturday) as per Odoo convention.');
            $table->enum('day_period', ['morning', 'afternoon'])->nullable();
            $table->integer('week_type')->default(0)->comment('Odoo: 0 = both weeks (default), 1 = week 1 (odd weeks), 2 = week 2 (even weeks). Used for bi-weekly schedules.');
            $table->date('date_from')->nullable()->comment('Optional start date for when the attendance is active.');
            $table->date('date_to')->nullable()->comment('Optional end date for when the attendance is active.');
            $table->time('start');
            $table->time('end');
            $table->boolean('active')->nullable()->comment('Whether the schedule detail is active (from Odoo).');
            $table->timestampTz('odoo_created_at')->nullable()->comment('Creation date of the record in Odoo (stored as UTC).');
            $table->timestampTz('odoo_updated_at')->nullable()->comment('Last update date of the record in Odoo (stored as UTC).');
            $table->timestamps();

            $table->foreign('odoo_schedule_id')->references('odoo_schedule_id')->on('schedules');
            $table->unique(['odoo_schedule_id', 'odoo_detail_id']);
            $table->index(['odoo_schedule_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_details');
    }
}
