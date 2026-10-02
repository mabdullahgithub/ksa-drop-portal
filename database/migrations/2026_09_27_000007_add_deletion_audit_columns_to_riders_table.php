<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removed riders go to the recycle bin. Same audit columns as the other
 * binned tables, stamped by RecordsDeletionAudit (via RiderObserver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->index('deleted_at', 'riders_deleted_at_index');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                ->constrained('users')->nullOnDelete();
            $table->string('deleted_ip', 45)->nullable()->after('deleted_by');
            $table->text('deleted_user_agent')->nullable()->after('deleted_ip');
            $table->string('deleted_device')->nullable()->after('deleted_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['deleted_ip', 'deleted_user_agent', 'deleted_device']);
            $table->dropIndex('riders_deleted_at_index');
        });
    }
};
