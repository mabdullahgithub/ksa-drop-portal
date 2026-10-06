<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_graveyard', function (Blueprint $table) {
            $table->id();
            // Stored lowercased and trimmed — see EmailGraveyard::normalize().
            $table->string('email')->unique();
            // smtp   = the mail server refused the address while we were sending
            // bounce = a bounce email reported it afterwards
            $table->string('source', 20);
            $table->string('status_code', 20)->nullable(); // e.g. 5.1.1
            $table->text('reason')->nullable();             // what the mail server said
            $table->unsignedInteger('blocked_count')->default(0); // sends stopped since
            $table->timestamp('last_blocked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_graveyard');
    }
};
