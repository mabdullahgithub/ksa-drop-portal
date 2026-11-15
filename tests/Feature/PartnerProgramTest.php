<?php

namespace Tests\Feature;

use App\Jobs\AwardPartnerPointsForDelivery;
use App\Models\Client;
use App\Models\Partner;
use App\Models\PartnerLead;
use App\Models\PartnerPayoutRequest;
use App\Models\PartnerSetting;
use App\Models\User;
use App\Services\Partners\PartnerApplications;
use App\Services\Partners\PartnerPayouts;
use App\Services\Partners\PartnerPoints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

class PartnerProgramTest extends TestCase
{
    use MakesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpStaff();
        Role::findOrCreate('partner');
        Storage::fake('local');
        // The local .env may have the portal switched off; these tests need it on.
        config()->set('app.client_portal_blocked', false);
    }

    private function makePartner(string $status = Partner::STATUS_APPROVED, ?Client $client = null): Partner
    {
        $user = $client?->user ?? User::factory()->create();
        $user->assignRole('partner');

        return Partner::create([
            'user_id' => $user->id,
            'client_id' => $client?->id,
            'type' => $client ? Partner::TYPE_CLIENT : Partner::TYPE_AGENCY,
            'status' => $status,
            'code' => 'P' . strtoupper(substr(uniqid(), -6)),
            'display_name' => 'Test Partner',
            'phone' => '0500000000',
            'approved_at' => $status === Partner::STATUS_APPROVED ? now() : null,
        ]);
    }

    private function referredClient(Partner $partner): Client
    {
        $client = $this->makeClient('Referred Store');
        $client->forceFill(['partner_id' => $partner->id, 'referred_at' => now()])->save();

        return $client;
    }

    // ── public pages and application ────────────────────────────────────────

    public function test_landing_and_apply_pages_are_public(): void
    {
        $this->get('/partners')->assertOk();
        $this->get('/partners/apply')->assertOk();
    }

    public function test_public_application_creates_pending_partner_and_signs_them_in(): void
    {
        $response = $this->post('/partners/apply', [
            'type' => 'agency',
            'name' => 'Sara Agency',
            'email' => 'sara@agency.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'phone' => '0501234567',
            'country' => 'SA',
            'company_name' => 'Sara Digital',
            'promotion_plan' => str_repeat('We onboard Shopify stores. ', 3),
            'motivation' => str_repeat('We want a fulfillment partner for our clients in Riyadh. ', 2),
            'id_document_type' => 'national_id',
            'id_document' => UploadedFile::fake()->image('id.jpg'),
            'code' => 'sara-digital',
            'terms' => '1',
        ]);

        $response->assertRedirect('/partners/apply');

        $partner = Partner::first();
        $this->assertNotNull($partner);
        $this->assertSame('pending', $partner->status);
        $this->assertSame('SARADIGITAL', $partner->code);
        $this->assertTrue($partner->user->hasRole('partner'));
        $this->assertNotNull($partner->id_document_path);
        Storage::disk('local')->assertExists($partner->id_document_path);

        $this->assertAuthenticatedAs($partner->user);

        // Pending applicants can look around, but cannot move money.
        $this->get('/partners/dashboard')->assertOk();
        $this->get('/partners/profile')->assertOk();
        $this->post('/partners/payouts', ['points' => 1000])->assertRedirect('/partners/pending');
        $this->get('/partners/pending')->assertOk();
    }

    public function test_application_works_with_only_the_required_fields(): void
    {
        $this->post('/partners/apply', [
            'type' => 'influencer',
            'name' => 'Lina',
            'email' => 'lina@creator.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'phone' => '0501112222',
            'country' => 'SA',
            'terms' => '1',
        ])->assertRedirect('/partners/apply');

        $partner = Partner::firstOrFail();
        $this->assertNull($partner->id_document_path);
        $this->assertFalse($partner->hasIdDocument());
    }

    public function test_application_stores_both_sides_of_the_id(): void
    {
        $this->post('/partners/apply', [
            'type' => 'agency',
            'name' => 'Omar',
            'email' => 'omar@agency.test',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'phone' => '0503334444',
            'country' => 'SA',
            'id_document_type' => 'iqama',
            'id_document' => UploadedFile::fake()->image('front.jpg'),
            'id_document_back' => UploadedFile::fake()->image('back.jpg'),
            'terms' => '1',
        ])->assertRedirect('/partners/apply');

        $partner = Partner::firstOrFail();
        Storage::disk('local')->assertExists($partner->id_document_path);
        Storage::disk('local')->assertExists($partner->id_document_back_path);
    }

    public function test_honeypot_drops_bot_applications(): void
    {
        $this->post('/partners/apply', ['website_url_confirm' => 'http://spam', 'type' => 'agency'])->assertRedirect('/partners');
        $this->assertSame(0, Partner::count());
    }

    public function test_register_now_redirects_to_partner_application(): void
    {
        $this->get('/register')->assertRedirect('/partners/apply');
    }

    // ── referral link and leads ─────────────────────────────────────────────

    public function test_referral_page_sets_cookie_and_stores_lead(): void
    {
        $partner = $this->makePartner();

        $this->get("/r/{$partner->code}")->assertOk()->assertCookie('ksad_ref', $partner->code);

        $this->post("/r/{$partner->code}/lead", [
            'name' => 'Omar',
            'phone' => '0559999999',
            'company' => 'Omar Store',
        ])->assertSessionHas('success');

        $lead = PartnerLead::first();
        $this->assertSame($partner->id, $lead->partner_id);
        $this->assertSame('new', $lead->status);
    }

    public function test_referral_link_of_unapproved_partner_goes_to_landing(): void
    {
        $partner = $this->makePartner(Partner::STATUS_PENDING);

        $this->get("/r/{$partner->code}")->assertRedirect('/partners');
    }

    public function test_admin_creating_client_with_partner_code_credits_the_partner(): void
    {
        $partner = $this->makePartner();
        $lead = $partner->leads()->create(['name' => 'Omar', 'phone' => '0559999999']);
        $admin = $this->staffWith($this->permissions(['create client', 'view client']));

        $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Omar',
            'email' => 'omar@store.test',
            'client_types' => ['fulfilment'],
            'company_name' => 'Omar Store',
            'lead_id' => $lead->id,
        ])->assertCreated();

        $client = Client::where('company_name', 'Omar Store')->first();
        $this->assertSame($partner->id, $client->partner_id);
        $this->assertNotNull($client->referred_at);
        $this->assertSame('converted', $lead->fresh()->status);
        $this->assertSame($client->id, $lead->fresh()->client_id);
    }

    public function test_unknown_partner_code_is_rejected_on_client_create(): void
    {
        $admin = $this->staffWith($this->permissions(['create client', 'view client']));

        $this->actingAs($admin)->postJson('/api/clients', [
            'name' => 'Omar',
            'email' => 'omar@store.test',
            'client_types' => ['fulfilment'],
            'company_name' => 'Omar Store',
            'partner_code' => 'NOPE1234',
        ])->assertStatus(422)->assertJsonValidationErrors('partner_code');
    }

    // ── points engine ───────────────────────────────────────────────────────

    public function test_delivery_awards_activation_then_per_delivery_points_once(): void
    {
        PartnerSetting::put(['activation_points' => 150, 'delivery_points' => 1, 'delivery_months' => 12, 'milestone_deliveries' => 0]);
        $partner = $this->makePartner();
        $client = $this->referredClient($partner);
        $shipment = $this->makeShipment($this->makeOrder($client));

        $shipment->markDelivered();
        $shipment->refresh();

        // The job runs inline (sync queue): run it again to prove idempotency.
        (new AwardPartnerPointsForDelivery($shipment))->handle(app(PartnerPoints::class));
        $shipment->markDelivered();

        $partner->refresh();
        $this->assertSame(151, $partner->points_balance);
        $this->assertSame(2, $partner->transactions()->count());
        $this->assertNotNull($client->fresh()->partner_activated_at);
    }

    public function test_delivery_outside_window_earns_nothing(): void
    {
        PartnerSetting::put(['activation_points' => 100, 'delivery_points' => 5, 'delivery_months' => 1, 'milestone_deliveries' => 0]);
        $partner = $this->makePartner();
        $client = $this->referredClient($partner);
        $client->forceFill(['partner_activated_at' => now()->subMonths(3)])->save();

        $shipment = $this->makeShipment($this->makeOrder($client));
        $shipment->markDelivered();

        $this->assertSame(0, $partner->fresh()->points_balance);
    }

    public function test_tier_multiplier_applies_to_delivery_points(): void
    {
        PartnerSetting::put(['activation_points' => 0, 'delivery_points' => 10, 'delivery_months' => 12, 'milestone_deliveries' => 0, 'silver_min_active_clients' => 1, 'silver_multiplier' => 1.5]);
        $partner = $this->makePartner();
        $client = $this->referredClient($partner);

        $this->makeShipment($this->makeOrder($client))->markDelivered();

        $partner->refresh();
        $this->assertSame('silver', $partner->tier);
        // Activation lifted the tier before the delivery points were worked out.
        $this->assertSame(15, $partner->points_balance);
    }

    public function test_unreferred_client_delivery_dispatches_nothing(): void
    {
        Queue::fake();
        $client = $this->makeClient();

        $this->makeShipment($this->makeOrder($client))->markDelivered();

        Queue::assertNotPushed(AwardPartnerPointsForDelivery::class);
    }

    public function test_approval_awards_welcome_bonus_once(): void
    {
        PartnerSetting::put(['approval_bonus_points' => 50]);
        $partner = $this->makePartner(Partner::STATUS_PENDING);
        $admin = $this->fullAccessUser();

        $apps = app(PartnerApplications::class);
        $apps->approve($partner, $admin);
        $apps->suspend($partner->fresh(), $admin, true);
        $apps->approve($partner->fresh(), $admin);

        $this->assertSame(50, $partner->fresh()->points_balance);
    }

    // ── payouts ─────────────────────────────────────────────────────────────

    public function test_payout_flow_deducts_on_approval_and_returns_on_rejection(): void
    {
        PartnerSetting::put(['min_redeem_points' => 100]);
        $partner = $this->makePartner();
        $partner->forceFill(['payout_method' => ['type' => 'bank', 'iban' => 'SA00', 'bank_name' => 'X', 'holder_name' => 'Y']])->save();
        app(PartnerPoints::class)->adjust($partner, 300, 'seed', $this->fullAccessUser());

        $payouts = app(PartnerPayouts::class);
        $admin = $this->fullAccessUser();

        $request = $payouts->request($partner->fresh(), 200);
        $this->assertSame(300, $partner->fresh()->points_balance);
        $this->assertSame(100, $payouts->available($partner->fresh()));

        $payouts->approve($request, $admin);
        $this->assertSame(100, $partner->fresh()->points_balance);

        $payouts->reject($request->fresh(), $admin, 'IBAN invalid');
        $this->assertSame(300, $partner->fresh()->points_balance);
        $this->assertSame(PartnerPayoutRequest::STATUS_REJECTED, $request->fresh()->status);
    }

    public function test_payout_below_minimum_is_refused(): void
    {
        PartnerSetting::put(['min_redeem_points' => 500]);
        $partner = $this->makePartner();
        $partner->forceFill(['payout_method' => ['type' => 'other', 'details' => 'STC Pay']])->save();
        app(PartnerPoints::class)->adjust($partner, 300, 'seed', $this->fullAccessUser());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PartnerPayouts::class)->request($partner->fresh(), 300);
    }

    // ── access control ──────────────────────────────────────────────────────

    public function test_partner_only_user_lands_in_partner_area(): void
    {
        $partner = $this->makePartner();

        $this->actingAs($partner->user)->get('/')->assertRedirect('/partners/dashboard');
        $this->actingAs($partner->user)->get('/partners/dashboard')->assertOk();
        $this->actingAs($partner->user)->get('/partners-admin')->assertForbidden();
        $this->actingAs($partner->user)->getJson('/api/partners')->assertForbidden();
    }

    public function test_client_who_is_also_partner_keeps_portal_and_can_open_partner_area(): void
    {
        $client = $this->makeClient();
        $client->user->assignRole('client');
        $partner = $this->makePartner(Partner::STATUS_APPROVED, $client);

        $this->actingAs($client->user)->get('/')->assertRedirect('/portal');
        $this->actingAs($client->user)->get('/partners/dashboard')->assertOk();
        $this->assertSame($client->id, $partner->client_id);
    }

    public function test_client_can_join_from_portal(): void
    {
        $client = $this->makeClient();
        $client->user->assignRole('client');

        $this->actingAs($client->user)->get('/portal/partner/join')->assertOk();
        $this->actingAs($client->user)->post('/portal/partner/join', [
            'code' => 'ACMEREF',
            'promotion_plan' => 'I know many store owners in Jeddah.',
            'terms' => '1',
        ])->assertRedirect('/partners/pending');

        $partner = Partner::where('client_id', $client->id)->first();
        $this->assertNotNull($partner);
        $this->assertSame('ACMEREF', $partner->code);
        $this->assertTrue($client->user->fresh()->hasRole('partner'));
    }

    public function test_refer_and_earn_page_covers_every_partner_state(): void
    {
        $this->withoutVite();
        $client = $this->makeClient();
        $client->user->assignRole('client');

        $this->actingAs($client->user)->get('/portal/refer-and-earn')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Portal/ReferAndEarn')->where('state', 'none')->where('partner', null));

        $partner = $this->makePartner(Partner::STATUS_APPROVED, $client);
        $this->referredClient($partner);

        $this->actingAs($client->user->fresh())->get('/portal/refer-and-earn')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('state', 'approved')
                ->where('partner.code', $partner->code)
                ->where('stats.referred_clients', 1)
                ->has('clients', 1));
    }

    public function test_admin_impersonating_a_client_can_view_the_join_page_but_not_submit(): void
    {
        $client = $this->makeClient();
        $client->user->assignRole('client');
        $admin = $this->staffWith($this->permissions(['impersonate client']));

        $session = ['impersonate.admin_id' => $admin->id, 'impersonate.client_id' => $client->id];

        $this->actingAs($admin)->withSession($session)->get('/portal/partner/join')->assertOk();
        $this->actingAs($admin)->withSession($session)->post('/portal/partner/join', [
            'promotion_plan' => 'I know many store owners in Jeddah.',
            'terms' => '1',
        ])->assertForbidden();
        $this->assertSame(0, Partner::where('client_id', $client->id)->count());
    }

    public function test_id_document_needs_its_own_permission(): void
    {
        $partner = $this->makePartner();
        $partner->forceFill(['id_document_path' => 'partners/1/id.jpg'])->save();
        Storage::disk('local')->put('partners/1/id.jpg', 'x');

        $viewer = $this->staffWith($this->permissions(['view partners']));
        $this->actingAs($viewer)->get("/api/partners/{$partner->id}")->assertOk();
        $this->actingAs($viewer)->get("/api/partners/{$partner->id}/document")->assertForbidden();

        $reviewer = $this->staffWith($this->permissions(['view partners', 'view partner documents']));
        $this->actingAs($reviewer)->get("/api/partners/{$partner->id}/document")->assertOk();
    }

    public function test_admin_can_approve_reject_and_adjust_via_api(): void
    {
        $partner = $this->makePartner(Partner::STATUS_PENDING);
        $admin = $this->fullAccessUser();

        $this->actingAs($admin)->postJson("/api/partners/{$partner->id}/approve")->assertOk();
        $this->assertSame('approved', $partner->fresh()->status);

        $this->actingAs($admin)->postJson("/api/partners/{$partner->id}/points", ['points' => -10, 'note' => 'test'])->assertOk();
        $this->assertSame(config('partners.approval_bonus_points') - 10, $partner->fresh()->points_balance);

        $this->actingAs($admin)->postJson("/api/partners/{$partner->id}/points", ['points' => 0, 'note' => 'x'])->assertStatus(422);

        $this->actingAs($admin)->get('/partners-admin')->assertOk();
    }

    public function test_partner_can_customize_code_once_and_old_code_keeps_working(): void
    {
        $partner = $this->makePartner();
        $old = $partner->code;
        $other = $this->makePartner();

        $this->actingAs($partner->user)->post('/partners/code', ['code' => 'ab'])->assertSessionHasErrors('code');
        $this->actingAs($partner->user)->post('/partners/code', ['code' => $other->code])->assertSessionHasErrors('code');

        $this->actingAs($partner->user)->post('/partners/code', ['code' => 'my-brand'])->assertSessionHasNoErrors();
        $partner->refresh();
        $this->assertSame('MYBRAND', $partner->code);
        $this->assertNotNull($partner->code_customized_at);

        // The old link still credits the same partner and nobody else can take it.
        $this->assertTrue(\App\Services\Partners\PartnerCode::find($old)->is($partner));
        $this->assertFalse(\App\Services\Partners\PartnerCode::isAvailable($old, $other->id));

        // One time only.
        $this->actingAs($partner->user)->post('/partners/code', ['code' => 'SECONDTRY'])->assertSessionHasErrors('code');
        $this->assertSame('MYBRAND', $partner->fresh()->code);
    }

    public function test_pending_partner_cannot_customize_code(): void
    {
        $partner = $this->makePartner(Partner::STATUS_PENDING);

        $this->actingAs($partner->user)->post('/partners/code', ['code' => 'MYBRAND'])->assertRedirect();
        $this->assertNotSame('MYBRAND', $partner->fresh()->code);
    }

    public function test_points_and_referrals_pages_render_with_their_data(): void
    {
        $partner = $this->makePartner();
        $this->referredClient($partner);

        $this->actingAs($partner->user)->get('/partners/points')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Partners/Points')->has('stats.monthly', 6)->has('program.tiers', 3));
        $this->actingAs($partner->user)->get('/partners/referrals')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Partners/Referrals')->has('clients', 1));
    }
}
