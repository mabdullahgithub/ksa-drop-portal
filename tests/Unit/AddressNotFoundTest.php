<?php

namespace Tests\Unit;

use App\Services\Mail\AddressNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddressNotFoundTest extends TestCase
{
    #[DataProvider('deadAddresses')]
    public function test_it_recognises_an_address_that_does_not_exist(?string $status, string $diagnostic): void
    {
        $this->assertTrue(AddressNotFound::matches($status, $diagnostic));
    }

    public static function deadAddresses(): array
    {
        return [
            'gmail unknown account' => ['5.1.1', '550-5.1.1 The email account that you tried to reach does not exist. Please try 550-5.1.1 double-checking the recipient\'s email address'],
            'gmail unknown domain' => ['4.0.0', 'DNS Error: DNS type \'mx\' lookup of nosuchdomain.example responded with code NXDOMAIN Domain name not found: nosuchdomain.example'],
            'gmail bad syntax at rcpt' => [null, '553 5.1.3 The recipient address <ali@@x.com> is not a valid RFC 5321 address'],
            'godaddy unknown recipient' => ['5.1.0', '550 5.1.0 <ali@example.com> Recipient not found.  <https://www.secureserver.net/help/fix-rejected-email-with-a-bounce-error-40685>'],
            'postfix unknown user' => [null, '550 5.1.1 <ali@example.com>: Recipient address rejected: User unknown in virtual mailbox table'],
            'office 365 unknown recipient' => ['5.4.1', '550 5.4.1 Recipient address rejected: Access denied. AS(201806281)'],
            'exchange resolver' => [null, '550 5.1.10 RESOLVER.ADR.RecipientNotFound; Recipient ali@example.com not found by SMTP address lookup'],
            'hotmail unknown mailbox' => ['5.5.0', '550 5.5.0 Requested action not taken: mailbox unavailable'],
            'yahoo unknown account' => [null, '554 delivery error: dd This user doesn\'t have a yahoo.com account (ali@yahoo.com)'],
            'no status, plain wording' => [null, '550 No such user here'],
        ];
    }

    #[DataProvider('liveAddresses')]
    public function test_it_leaves_other_failures_alone(?string $status, string $diagnostic): void
    {
        $this->assertFalse(AddressNotFound::matches($status, $diagnostic));
    }

    public static function liveAddresses(): array
    {
        return [
            // Returned for every recipient when our SMTP login is wrong —
            // burying on this would empty the user base.
            'relay denied' => [null, '554 5.7.1 <ali@example.com>: Relay access denied'],
            'spam block' => ['5.7.1', '550 5.7.1 Message rejected as spam by content filtering'],
            'policy block worded like a missing mailbox' => ['5.7.1', '550 5.7.1 Recipient address rejected: Access denied'],
            'mailbox full' => ['5.2.2', '552 5.2.2 The email account that you tried to reach is over quota'],
            'gmail rate limit' => ['5.2.1', '550 5.2.1 The user you are trying to contact is receiving mail at a rate that prevents additional messages'],
            'message too large' => ['5.3.4', '552 5.3.4 Message size exceeds fixed limit'],
            'our own sender refused' => [null, '550 5.1.0 <portal@example.com>: Sender address rejected: User unknown in virtual mailbox table'],
            'our own sender domain' => [null, '550 5.1.8 Sender address rejected: Domain not found'],
            'nothing to go on' => ['5.0.0', '550 Requested action not taken'],
        ];
    }
}
