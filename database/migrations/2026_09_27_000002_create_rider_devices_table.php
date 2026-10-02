<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A phone a rider is signed in on. The rider app's long-lived cookie holds
     * the plain token; only its SHA-256 is stored, so a database leak can't be
     * replayed as a sign-in. Revoking the row signs that phone out on its next
     * request.
     */
    public function up(): void
    {
        Schema::create('rider_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('sign_in_method', 20); // activation | pin
            $table->string('platform', 20)->nullable(); // android | ios | other
            $table->boolean('standalone')->default(false);
            $table->string('user_agent', 500)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 50)->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_devices');
    }
};
