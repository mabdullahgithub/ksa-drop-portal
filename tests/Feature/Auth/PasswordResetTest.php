<?php

namespace Tests\Feature\Auth;

use App\Mail\Auth\ResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        // User::sendPasswordResetNotification is overridden to send the app's
        // own branded mailable through EmailService, so Laravel's default
        // ResetPassword notification is never dispatched.
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        // Queued, not sent: ResetPasswordMail implements ShouldQueue, so it only
        // reaches the transport once a worker picks it up. That is why the app's
        // default queue connection must stay sync — a password reset cannot be
        // left waiting on a worker.
        Mail::assertQueued(ResetPasswordMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user  = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->get('/reset-password/'.$token)->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $user->fresh()->password));
    }
}
