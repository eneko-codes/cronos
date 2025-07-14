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
            $table->enum('day_period', ['morning', 'afternoon']);
            $table->integer('week_type')->default(0)->comment('Odoo: 0 = both weeks (default), 1 = week 1 (odd weeks), 2 = week 2 (even weeks). Used for bi-weekly schedules.');
            $table->date('date_from')->nullable()->comment('Optional start date for when the attendance is active.');
            $table->date('date_to')->nullable()->comment('Optional end date for when the attendance is active.');
            $table->time('start');
            $table->time('end');
            $table->boolean('active')->nullable()->comment('Whether the schedule detail is active (from Odoo).');
            $table->unsignedBigInteger('odoo_created_by')->nullable()->comment('ID of the user who created the record in Odoo.');
            $table->unsignedBigInteger('odoo_last_updated_by')->nullable()->comment('ID of the user who last updated the record in Odoo');
            $table->timestamp('odoo_created_at')->nullable()->comment('Creation date of the record in Odoo (UTC).');
            $table->timestamp('odoo_updated_at')->nullable()->comment('Last update date of the record in Odoo (UTC).');
            $table->timestamps();

            $table->foreign('odoo_schedule_id')->references('odoo_schedule_id')->on('schedules');
            $table->unique(['odoo_schedule_id', 'odoo_detail_id']);
            $table->index(['odoo_schedule_id', 'weekday']);
            $table->foreign('odoo_created_by')->references('odoo_id')->on('users')->nullOnDelete();
            $table->foreign('odoo_last_updated_by')->references('odoo_id')->on('users')->nullOnDelete();
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
