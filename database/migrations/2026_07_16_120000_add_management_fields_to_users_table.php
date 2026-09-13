<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add reusable account classification and availability state.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('name', 'users_name_unique');
            $table->string('user_type', 50)
                ->default('viewer')
                ->after('email')
                ->comment('Workflow classification backed by App\\Enums\\UserType; permissions remain authoritative.')
                ->index();
            $table->boolean('is_active')
                ->default(true)
                ->after('user_type')
                ->comment('Whether the account may authenticate and participate in managed workflows.')
                ->index();
        });
    }

    /**
     * Remove the account-management extension.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_name_unique');
            $table->dropIndex(['user_type']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['user_type', 'is_active']);
        });
    }
};
