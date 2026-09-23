<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who deleted a row, from where, and on what.
 *
 * Stored on the row itself so the recycle bin can show it without a join per
 * listing, and cleared on restore so a restored record does not keep claiming
 * it is deleted by someone.
 */
return new class extends Migration
{
    private const TABLES = ['orders', 'clients', 'client_products', 'products'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('deleted_by')->nullable()->after('deleted_at')
                    ->constrained('users')->nullOnDelete();
                // 45 chars covers IPv6, including IPv4-mapped form.
                $blueprint->string('deleted_ip', 45)->nullable()->after('deleted_by');
                // Full UA string, unabridged, as asked -- text because UA
                // strings routinely run past 255 characters.
                $blueprint->text('deleted_user_agent')->nullable()->after('deleted_ip');
                // Readable summary derived from the UA at delete time, kept
                // alongside the raw string so the listing needs no parsing.
                $blueprint->string('deleted_device')->nullable()->after('deleted_user_agent');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('deleted_by');
                $blueprint->dropColumn(['deleted_ip', 'deleted_user_agent', 'deleted_device']);
            });
        }
    }
};
