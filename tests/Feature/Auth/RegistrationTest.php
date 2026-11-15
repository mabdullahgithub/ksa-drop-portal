<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public self-registration is retired: people apply through the partner
 * program, and clients are created by staff.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_sends_visitors_to_the_partner_application(): void
    {
        $this->get('/register')->assertRedirect('/partners/apply');
    }

    public function test_posting_to_register_does_not_create_users(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'test@example.com')->count());
    }
}
