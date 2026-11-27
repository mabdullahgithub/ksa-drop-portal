<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Where each message was sent from (see App\Services\Chat\ChatOrigin), and
 * the permission to see it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('origin')->nullable()->after('shared_items');
        });

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('origin');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', 'view chat message details')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
