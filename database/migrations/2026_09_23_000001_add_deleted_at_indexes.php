<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * $table->softDeletes() creates the column but no index. The recycle bin reads
 * these four tables by deleted_at on every page load (three listings plus a
 * counts call for the tab badges), so without an index each one is a full scan.
 * Also speeds up every ordinary query, which all filter deleted_at IS NULL.
 */
return new class extends Migration
{
    private const TABLES = ['orders', 'clients', 'client_products', 'products'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index('deleted_at', "{$table}_deleted_at_index");
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex("{$table}_deleted_at_index");
            });
        }
    }
};
