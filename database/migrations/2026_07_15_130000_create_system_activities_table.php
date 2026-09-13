<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the append-only audit trail used by every CMS module.
     */
    public function up(): void
    {
        Schema::create('system_activities', function (Blueprint $table): void {
            $table->id()->comment('Primary key for the system activity record.');
            $table->uuid('batch_uuid')->nullable()->comment('Optional correlation UUID shared by related activity records.');
            $table->foreignId('user_id')->nullable()->comment('Actor responsible for the activity; null denotes a system process.')->constrained()->nullOnDelete();
            $table->string('activity_type', 150)->comment('Lowercase dot-notated event name such as post.published.');
            $table->string('severity', 20)->default('info')->comment('Operational severity: info, notice, warning, error, or critical.');
            $table->string('source', 50)->default('application')->comment('Execution source such as web, console, queue, scheduler, or integration.');
            $table->text('description')->comment('Human-readable explanation of the recorded activity.');
            $table->string('subject_type', 191)->nullable()->comment('Morph class of the domain record affected by the activity.');
            $table->string('subject_id', 64)->nullable()->comment('Integer, UUID, or ULID key of the affected domain record.');
            $table->string('ip_address', 45)->nullable()->comment('IPv4 or IPv6 address associated with the request.');
            $table->text('user_agent')->nullable()->comment('Truncated client user-agent string associated with the request.');
            $table->string('request_method', 12)->nullable()->comment('HTTP method associated with the activity, when applicable.');
            $table->string('route_name', 191)->nullable()->comment('Named Laravel route associated with the activity, when available.');
            $table->text('request_url')->nullable()->comment('Request URL without query parameters to avoid leaking credentials.');
            $table->json('properties')->nullable()->comment('Sanitized structured context required to understand the activity.');
            $table->timestamp('created_at')->useCurrent()->comment('UTC-compatible timestamp at which the activity was recorded.');

            $table->index('batch_uuid');
            $table->index(['user_id', 'created_at']);
            $table->index(['activity_type', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Remove the system activity audit trail.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_activities');
    }
};
