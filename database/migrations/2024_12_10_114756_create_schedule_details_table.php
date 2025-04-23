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
        Schema::create('schedule_details', function (Blueprint $table) {
            $table->comment('Stores details of each schedule fetched from Odoo, linked by odoo_schedule_id and odoo_detail_id.');
            $table->id();
            $table->unsignedBigInteger('odoo_schedule_id');
            $table->unsignedBigInteger('odoo_detail_id');
            $table->tinyInteger('weekday'); // 0-6 Sunday-Saturday
            $table->enum('day_period', ['morning', 'afternoon']);
            $table->time('start');
            $table->time('end');
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
