<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner may pick their own code once. Every code they have held is kept,
 * so links already shared keep working and nobody else can take the old one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->timestamp('code_customized_at')->nullable()->after('code');
        });

        Schema::create('partner_code_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_code_history');

        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('code_customized_at');
        });
    }
};
