<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_external_identities', function (Blueprint $table): void {
            $table->comment('Maps local users to their identities on external platforms.');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 50)->comment('External platform identifier (odoo, desktime, proofhub, systempin)');
            $table->string('external_id')->comment('User ID on the external platform');
            $table->string('external_email')->nullable()->comment('Email used on the external platform');
            $table->boolean('is_manual_link')->default(false)->comment('Whether this link was created manually by an admin');
            $table->string('linked_by')->nullable()->comment('How the identity was linked: email, name, manual');
            $table->timestamps();

            // Composite unique constraint: one external ID per platform
            $table->unique(['platform', 'external_id'], 'uei_platform_external_id_unique');

            // Composite unique constraint: one platform link per user
            $table->unique(['user_id', 'platform'], 'uei_user_platform_unique');

            // Index for email lookups
            $table->index(['platform', 'external_email'], 'uei_platform_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_external_identities');
    }
};
