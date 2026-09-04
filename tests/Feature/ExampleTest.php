<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_url_sends_a_guest_to_the_login_screen(): void
    {
        // There is no public landing page: "/" routes a guest at the dashboard,
        // which the auth middleware then bounces to login. The scaffolded
        // version of this test asserted a 200 the app has never returned.
        $this->get('/')->assertRedirect();

        $this->followingRedirects()->get('/')->assertOk()->assertSee('login', false);
    }
}
