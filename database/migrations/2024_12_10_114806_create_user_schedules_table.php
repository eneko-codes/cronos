<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * References schedules by odoo_schedule_id.
     */
    public function up(): void
    {
        Schema::create('user_schedules', function (Blueprint $table): void {
            $table->comment('Stores the historical schedules of each user, referencing odoo_schedule_id from schedules.');
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('odoo_schedule_id'); // Reference to schedule's primary key
            $table->timestampTz('effective_from')->comment('Schedule effective start date (stored as UTC)');
            $table->timestampTz('effective_until')->nullable()->comment('Schedule effective end date (stored as UTC)');
            $table->timestamps();

            $table->foreign('odoo_schedule_id')->references('odoo_schedule_id')->on('schedules');
            $table->unique(['user_id', 'odoo_schedule_id', 'effective_from']);
            $table->index(['effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_schedules');
    }
}
