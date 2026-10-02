<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Call disposition — the outcome of the confirmation call an ops agent places
 * before an order is committed to fulfilment.
 *
 * This is deliberately a separate axis from `fulfillment_status`: an order can
 * be `unfulfilled` + `no_answer`, or `unfulfilled` + `confirmed`. Overloading
 * the existing status enum would have broken every query and filter that
 * assumes the Shopify-shaped four values.
 *
 * `no_answer` is the trigger for the WhatsApp confirmation flow — see
 * {@see \App\Observers\OrderObserver} and {@see \App\Jobs\SendWhatsAppOrderMessageJob}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('call_status')->default('not_called')->after('fulfillment_status')->index();
            $table->unsignedSmallInteger('call_attempts')->default(0)->after('call_status');
            $table->timestamp('last_called_at')->nullable()->after('call_attempts');
            $table->text('call_notes')->nullable()->after('last_called_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['call_status', 'call_attempts', 'last_called_at', 'call_notes']);
        });
    }
};
