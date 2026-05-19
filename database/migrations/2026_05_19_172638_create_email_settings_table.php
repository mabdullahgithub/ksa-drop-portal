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
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->enum('driver', ['smtp', 'sendmail', 'mailgun', 'ses', 'postmark', 'log'])->default('smtp');
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // Will be encrypted
            $table->enum('encryption', ['tls', 'ssl', 'none'])->nullable()->default('tls');
            $table->string('from_address');
            $table->string('from_name');
            $table->string('test_email')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('test_status', ['success', 'failed'])->nullable();
            $table->text('test_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_settings');
    }
};
