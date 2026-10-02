<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KSA Express riders. Deliberately not `users`: a rider signs in to the
     * rider app only (activation link, or phone + PIN), never the portal, and
     * most have no email — which `users` requires.
     *
     * Only name and phone are required; everything else is filled in when the
     * admin has it.
     */
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Normalised to +9665XXXXXXXX (App\Support\SaudiPhone) so the
            // unique index and the PIN login both match however it was typed.
            $table->string('phone', 20)->unique();

            $table->string('name_ar')->nullable();
            // Private disk; streamed to staff and to the rider's own app.
            $table->string('photo_path')->nullable();
            $table->text('national_id')->nullable(); // encrypted cast
            $table->string('nationality', 100)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate', 50)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->date('license_expiry')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city', 100)->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->text('iban')->nullable(); // encrypted cast
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('active')->index();

            // Fallback sign-in when the activation link can't be used. Hashed;
            // the admin sees the PIN once when generating it.
            $table->string('pin')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->unsignedSmallInteger('pin_failed_attempts')->default(0);

            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
