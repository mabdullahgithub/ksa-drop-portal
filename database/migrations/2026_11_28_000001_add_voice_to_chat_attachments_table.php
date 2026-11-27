<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voice messages are attachments recorded in the browser. The length is
     * kept because browser recordings often don't carry it in the file.
     */
    public function up(): void
    {
        Schema::table('chat_attachments', function (Blueprint $table) {
            $table->boolean('is_voice')->default(false)->after('size');
            $table->unsignedSmallInteger('duration_seconds')->nullable()->after('is_voice');
        });
    }

    public function down(): void
    {
        Schema::table('chat_attachments', function (Blueprint $table) {
            $table->dropColumn(['is_voice', 'duration_seconds']);
        });
    }
};
