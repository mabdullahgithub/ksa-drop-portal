<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('saudiNumbers')]
    public function test_it_normalises_saudi_numbers(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($input));
    }

    public static function saudiNumbers(): array
    {
        return [
            'local with trunk zero' => ['0501234567', '+966501234567'],
            'bare national number' => ['501234567', '+966501234567'],
            'already e164' => ['+966501234567', '+966501234567'],
            'country code, no plus' => ['966501234567', '+966501234567'],
            'double-zero international prefix' => ['00966501234567', '+966501234567'],
            'spaces and dashes' => ['+966 50-123 4567', '+966501234567'],
            'parentheses' => ['(966) 50 1234567', '+966501234567'],
            'arabic-indic digits' => ['٠٥٠١٢٣٤٥٦٧', '+966501234567'],

            // Refusing to guess matters more than coverage here: a wrong guess
            // sends an order confirmation to a stranger.
            'too short' => ['12345', null],
            'too long' => ['05012345678901', null],
            'empty' => ['', null],
            'letters only' => ['not a phone', null],
        ];
    }

    public function test_it_accepts_other_gulf_country_codes(): void
    {
        $this->assertSame('+971501234567', PhoneNumber::toE164('+971501234567'));
        $this->assertSame('+96512345678', PhoneNumber::toE164('96512345678'));
    }

    public function test_it_handles_null(): void
    {
        $this->assertNull(PhoneNumber::toE164(null));
    }

    public function test_it_wraps_and_unwraps_twilio_whatsapp_addresses(): void
    {
        $this->assertSame('whatsapp:+966501234567', PhoneNumber::toWhatsAppAddress('+966501234567'));

        // Idempotent — the sender number is stored with the prefix already on it.
        $this->assertSame('whatsapp:+966501234567', PhoneNumber::toWhatsAppAddress('whatsapp:+966501234567'));

        $this->assertSame('+966501234567', PhoneNumber::fromWhatsAppAddress('whatsapp:+966501234567'));
        $this->assertSame('+966501234567', PhoneNumber::fromWhatsAppAddress('+966501234567'));
        $this->assertNull(PhoneNumber::fromWhatsAppAddress(null));
    }
}
