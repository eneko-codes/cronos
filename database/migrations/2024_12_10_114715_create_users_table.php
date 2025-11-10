<?php

use App\Enums\RoleType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->comment('Stores the users of the application.');
            $table->id();
            $table->string('name')->index();
            $table->string('email')->unique()->index();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('odoo_id')->nullable()->unique();
            $table->unsignedBigInteger('proofhub_id')->nullable()->unique();
            $table->unsignedBigInteger('desktime_id')->nullable()->unique();
            $table->string('systempin_id')->nullable()->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('timezone')->nullable();
            $table->string('user_type')->default(RoleType::User->value);
            $table->boolean('do_not_track')->default(false);
            $table->boolean('muted_notifications')->default(false);
            $table->boolean('is_active')->default(true)->comment('Reflects the active status from Odoo');
            $table->string('job_title')->nullable();
            $table->unsignedBigInteger('odoo_manager_id')->nullable();
            $table->rememberToken()->nullable();

            $table
                ->foreign('department_id')
                ->references('odoo_department_id')
                ->on('departments')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
