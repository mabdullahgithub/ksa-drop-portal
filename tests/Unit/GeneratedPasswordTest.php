<?php

namespace Tests\Unit;

use App\Support\GeneratedPassword;
use PHPUnit\Framework\TestCase;

class GeneratedPasswordTest extends TestCase
{
    public function test_it_is_ten_characters_of_every_kind_and_no_space(): void
    {
        for ($i = 0; $i < 500; $i++) {
            $password = GeneratedPassword::make();

            $this->assertSame(10, strlen($password), $password);
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
            $this->assertDoesNotMatchRegularExpression('/\s/', $password);
        }
    }

    public function test_no_two_are_the_same(): void
    {
        $passwords = array_map(fn () => GeneratedPassword::make(), range(1, 200));

        $this->assertCount(200, array_unique($passwords));
    }
}
