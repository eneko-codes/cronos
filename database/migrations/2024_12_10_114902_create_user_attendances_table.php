<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('date');
            $table->timestampTz('clock_in')->nullable()->comment('UTC timestamp for clock in');
            $table->timestampTz('clock_out')->nullable()->comment('UTC timestamp for clock out');
            $table->integer('duration_seconds')->default(0); // Duration of this specific segment
            $table->boolean('is_remote');
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'date', 'clock_in']);
            $table->index(['clock_in', 'clock_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_attendances');
    }
}
