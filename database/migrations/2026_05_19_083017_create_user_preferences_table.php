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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('toast_position', [
                'top-left',
                'top-center',
                'top-right',
                'bottom-left',
                'bottom-center',
                'bottom-right'
            ])->default('bottom-right');
            $table->enum('notification_type', ['all', 'mentions', 'none'])->default('all');
            $table->boolean('mobile_notifications')->default(false);
            $table->boolean('communication_emails')->default(false);
            $table->boolean('social_emails')->default(true);
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('security_emails')->default(true);
            $table->boolean('in_app_notifications')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
