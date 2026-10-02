<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/**
 * Browser-facing errors render the designed error page and nothing technical:
 * no exception class, message or trace on screen, even with APP_DEBUG on. The
 * cause is logged under a reference the user can quote to support.
 */
class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => true]);

        Route::middleware('web')->get('/__test/boom', function () {
            throw new RuntimeException('SQLSTATE secret connection detail');
        });
    }

    public function test_server_error_shows_no_technical_detail_and_is_logged(): void
    {
        Log::spy();

        $response = $this->get('/__test/boom');

        $response->assertStatus(500)
            ->assertDontSee('SQLSTATE secret connection detail')
            ->assertDontSee('RuntimeException')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Error')
                ->where('status', 500)
                ->whereType('reference', 'string')
                ->missing('detail'));

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context) => str_starts_with($message, 'Unhandled exception [')
                && $context['exception'] === RuntimeException::class
                && $context['message'] === 'SQLSTATE secret connection detail');
    }

    public function test_statuses_without_their_own_copy_still_get_the_error_page(): void
    {
        $this->post('/__test/boom')
            ->assertStatus(405)
            ->assertDontSee('MethodNotAllowedHttpException')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/Error')
                ->where('status', 405)
                ->where('reference', null));
    }

    public function test_json_requests_keep_machine_readable_errors(): void
    {
        $this->getJson('/__test/boom')
            ->assertStatus(500)
            ->assertJsonStructure(['message']);
    }
}
