<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('courier')->default('jnt_express');
            $table->string('tracking_number')->nullable()->index();
            $table->string('txlogistic_id')->unique();
            $table->string('sorting_code')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('courier_status')->nullable();
            $table->string('courier_status_description')->nullable();
            $table->json('tracking_history')->nullable();
            $table->json('api_response')->nullable();
            $table->string('label_url')->nullable();
            $table->decimal('weight', 8, 2)->default(0.5);
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('service_type')->default('02');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['courier', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
