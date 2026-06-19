<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->text('exception_note')->nullable()->after('error_message');
            $table->timestamp('exception_escalated_at')->nullable()->after('exception_note');
            $table->boolean('otp_verified')->default(false)->after('exception_escalated_at');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_verified');
            $table->string('return_tracking_number')->nullable()->after('otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['exception_note', 'exception_escalated_at', 'otp_verified', 'otp_verified_at', 'return_tracking_number']);
        });
    }
};
