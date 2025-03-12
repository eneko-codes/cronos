<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            // Alert type (e.g., schedule_duplicates, sync_error, employee_specific, etc.)
            $table->string('type');
            // Target audience: null for all admins, specific user_id for employee-specific alerts
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            // Whether the alert is for admin users only
            $table->boolean('admin_only')->default(false);
            // Alert title 
            $table->string('title');
            // Alert message or description
            $table->text('message')->nullable();
            // Additional data in JSON format (for flexible storage of context)
            $table->json('data')->nullable();
            // Whether the alert has been resolved
            $table->boolean('resolved')->default(false);
            // When the alert should expire (optional)
            $table->timestamp('expires_at')->nullable();
            // Who resolved the alert and when
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            // Standard created and updated timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
