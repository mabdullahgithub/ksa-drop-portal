<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent audit trail for the recycle bin.
 *
 * The per-row deleted_by/deleted_ip columns answer "who deleted this" for
 * anything still in the bin -- but a permanent delete destroys the row and
 * those columns with it. This table outlives the record, so "who purged that
 * client, from which machine" stays answerable afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_logs', function (Blueprint $table) {
            $table->id();

            // Not a polymorphic relation: the subject is routinely gone by the
            // time anyone reads this, so the type/id are kept as plain values
            // plus a human label captured while the record still existed.
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label')->nullable();

            $table->string('action', 16); // deleted | restored | purged

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable(); // kept for when the user is later removed
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_logs');
    }
};
