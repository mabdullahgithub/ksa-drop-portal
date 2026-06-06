<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->constrained()->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['connector_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_settings');
    }
};
