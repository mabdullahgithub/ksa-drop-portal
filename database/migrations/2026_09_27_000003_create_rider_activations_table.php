<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time activation links. The link carries the plain token; only its
     * SHA-256 is stored. Issuing a new link voids every unclaimed one.
     */
    public function up(): void
    {
        Schema::create('rider_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('rider_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_activations');
    }
};
