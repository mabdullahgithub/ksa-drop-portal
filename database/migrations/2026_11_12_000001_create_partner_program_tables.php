<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The partner program: people who bring sellers to KSA Drop and earn points
 * for it. A partner is a portal user (login, 2FA, password reset all reused)
 * with the `partner` role and a row here; a client can be one too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Set when an existing client switches partner mode on.
            $table->foreignId('client_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('type', 20)->index(); // client | agency | influencer
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected | suspended
            // Uppercase, in the referral link and typed by hand on WhatsApp.
            $table->string('code', 20)->unique();

            $table->string('display_name');
            $table->string('company_name')->nullable();
            $table->string('phone', 20);
            $table->string('whatsapp', 20)->nullable();
            $table->string('country', 2)->default('SA');
            $table->string('city', 100)->nullable();
            $table->string('website')->nullable();
            $table->json('social_links')->nullable();
            $table->string('audience_size', 30)->nullable();
            $table->text('promotion_plan')->nullable();
            $table->text('motivation')->nullable();

            // Private disk; streamed only to staff who may see documents.
            $table->string('id_document_path')->nullable();
            $table->string('id_document_type', 20)->nullable(); // national_id | iqama | passport
            $table->string('logo_path')->nullable();

            $table->string('tier', 20)->default('bronze');
            $table->integer('points_balance')->default(0);
            $table->text('payout_method')->nullable(); // encrypted cast, not JSON

            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('applied_ip', 45)->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partner_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('store_url')->nullable();
            $table->string('monthly_orders', 30)->nullable();
            $table->string('source', 20)->default('link'); // link | code | manual
            $table->string('status', 20)->default('new')->index(); // new | contacted | converted | lost
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->timestamp('referred_at')->nullable()->after('partner_id');
            // First delivered shipment: the referral is "activated" from here.
            $table->timestamp('partner_activated_at')->nullable()->after('referred_at');
        });

        Schema::create('partner_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->integer('points'); // signed: payouts and reversals are negative
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // One award per event, however many times the event is replayed.
            $table->unique(['partner_id', 'type', 'reference_type', 'reference_id'], 'partner_points_once');
        });

        Schema::create('partner_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('status', 20)->default('requested')->index(); // requested | approved | paid | rejected
            $table->text('payout_method'); // encrypted snapshot, not JSON
            $table->text('partner_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('proof_path')->nullable();
            $table->decimal('amount_paid', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settings');
        Schema::dropIfExists('partner_payout_requests');
        Schema::dropIfExists('partner_point_transactions');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropColumn(['referred_at', 'partner_activated_at']);
        });
        Schema::dropIfExists('partner_leads');
        Schema::dropIfExists('partners');
    }
};
