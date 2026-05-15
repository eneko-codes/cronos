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
            // Primary authentication and notification email - synced from Odoo work_email
            // Required by Laravel 12 for Auth::attempt() and notifications
            // All notifications are sent to this email address
            $table->string('email')->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('timezone')->nullable();
            $table->string('user_type')->default(RoleType::User->value);
            $table->boolean('do_not_track')->default(false);
            $table->boolean('muted_notifications')->default(false);
            $table->boolean('is_active')->default(true)->comment('Reflects the active status from Odoo');
            $table->timestamp('manually_archived_at')->nullable()->comment('Timestamp when user was manually archived by an admin. Prevents Odoo sync from reactivating the user.');
            $table->string('job_title')->nullable();
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
