<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Public signups as a queue on the Requests page: which clients came in that
 * way and what was decided, plus the tab's permissions. `superadmin` and
 * `developer` get them here; every other role gets them in the role editor.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['view signup requests', 'approve signup requests', 'reject signup requests'];

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('signed_up_at')->nullable()->index();
            $table->timestamp('signup_reviewed_at')->nullable();
            $table->foreignId('signup_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signup_rejection_reason', 1000)->nullable();
        });

        // Anyone who signed up before this ran.
        DB::table('clients')->where('status', 'pending')->update(['signed_up_at' => DB::raw('created_at')]);

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signup_reviewed_by');
            $table->dropIndex(['signed_up_at']);
            $table->dropColumn(['signed_up_at', 'signup_reviewed_at', 'signup_rejection_reason']);
        });
    }
};
