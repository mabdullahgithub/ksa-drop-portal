<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which clients a team member handles. `client_access` says whether they
     * are limited at all, so taking away someone's last client leaves them
     * seeing none rather than every client.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('client_access', 20)->default('all')->after('password');
        });

        Schema::create('client_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user_access');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('client_access');
        });
    }
};
