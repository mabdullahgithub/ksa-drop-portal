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
        Schema::table('user_preferences', function (Blueprint $table) {
            // Check and add columns only if they don't exist
            if (!Schema::hasColumn('user_preferences', 'verify_email')) {
                $table->boolean('verify_email')->default(true)->after('user_management_emails');
            }
            if (!Schema::hasColumn('user_preferences', 'reset_password')) {
                $table->boolean('reset_password')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'password_changed')) {
                $table->boolean('password_changed')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'two_factor_disabled')) {
                $table->boolean('two_factor_disabled')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'welcome_user')) {
                $table->boolean('welcome_user')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'user_created_admin')) {
                $table->boolean('user_created_admin')->default(false);
            }
            if (!Schema::hasColumn('user_preferences', 'user_updated')) {
                $table->boolean('user_updated')->default(false);
            }
            if (!Schema::hasColumn('user_preferences', 'welcome')) {
                $table->boolean('welcome')->default(true);
            }
            if (!Schema::hasColumn('user_preferences', 'settings_updated')) {
                $table->boolean('settings_updated')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $columns = [
                'verify_email',
                'reset_password',
                'password_changed',
                'two_factor_enabled',
                'two_factor_disabled',
                'welcome_user',
                'user_created_admin',
                'user_updated',
                'welcome',
                'settings_updated',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('user_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
