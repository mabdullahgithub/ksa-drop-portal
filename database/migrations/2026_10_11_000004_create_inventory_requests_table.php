<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A change a client asked for on one of their verified products: more
     * (or less) stock, or different details. The product itself is left
     * alone until someone on the team approves it.
     *
     * A product has at most one pending request of each type; saving the
     * product again replaces it (see InventoryRequestRecorder). Decided rows
     * are kept: they are the history.
     */
    public function up(): void
    {
        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_product_id')->constrained()->cascadeOnDelete();

            $table->string('type', 10); // stock | details
            $table->string('status', 10)->default('pending'); // pending | approved | rejected | cancelled

            // Goes up each time the client replaces a pending request, so an
            // approval of what was on screen a minute ago can be told apart.
            $table->unsignedInteger('revision')->default(1);

            // Stock: the quantity the client typed, and the stock their form
            // showed. The difference is what they are asking for.
            $table->integer('requested_quantity')->nullable();
            $table->integer('stock_at_request')->nullable();
            // Filled on approval. Signed: above zero came in, below zero was
            // taken off.
            $table->integer('received_quantity')->nullable();
            $table->integer('stock_before')->nullable();
            $table->integer('stock_after')->nullable();

            // Details: the fields that change (and remove_image_ids), and
            // what those fields held when the client asked.
            $table->json('changes')->nullable();
            $table->json('original')->nullable();

            $table->text('rejection_reason')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['client_product_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_requests');
    }
};
