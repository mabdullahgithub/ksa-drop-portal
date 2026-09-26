<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a user sends it to the recycle bin instead of removing the row.
 *
 * Same shape as the other binned tables: deleted_at (indexed, since every
 * login and user lookup now filters on it) plus the deletion audit columns
 * that RecordsDeletionAudit stamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'users_deleted_at_index');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
            $table->string('deleted_ip', 45)->nullable()->after('deleted_by');
            $table->text('deleted_user_agent')->nullable()->after('deleted_ip');
            $table->string('deleted_device')->nullable()->after('deleted_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deleted_ip', 'deleted_user_agent', 'deleted_device']);
            $table->dropIndex('users_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
