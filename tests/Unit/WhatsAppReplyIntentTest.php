<?php

namespace Tests\Unit;

use App\Services\WhatsApp\ReplyIntent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WhatsAppReplyIntentTest extends TestCase
{
    #[DataProvider('replies')]
    public function test_it_classifies_replies(string $body, string $expected): void
    {
        $this->assertSame($expected, ReplyIntent::classify($body));
    }

    public static function replies(): array
    {
        return [
            // The templated happy path.
            'digit 1' => ['1', ReplyIntent::CONFIRM],
            'digit 2' => ['2', ReplyIntent::UPDATE_ADDRESS],
            'digit 3' => ['3', ReplyIntent::CANCEL],

            'english yes' => ['yes', ReplyIntent::CONFIRM],
            'english confirm' => ['Confirm please', ReplyIntent::CONFIRM],
            'english cancel' => ['cancel it', ReplyIntent::CANCEL],
            'english address' => ['wrong address', ReplyIntent::UPDATE_ADDRESS],

            'arabic yes' => ['نعم', ReplyIntent::CONFIRM],
            'arabic tamam' => ['تمام', ReplyIntent::CONFIRM],
            'arabic cancel' => ['الغاء', ReplyIntent::CANCEL],
            'arabic address' => ['العنوان غلط', ReplyIntent::UPDATE_ADDRESS],

            // Cancel outranks confirm so "no" isn't swallowed by the "ok" in
            // a phrase like "not ok".
            'negation containing ok' => ['not ok', ReplyIntent::CANCEL],

            'empty' => ['', ReplyIntent::UNKNOWN],
            'gibberish' => ['???', ReplyIntent::UNKNOWN],
        ];
    }

    public function test_a_long_unmatched_reply_is_treated_as_an_address(): void
    {
        $this->assertSame(
            ReplyIntent::UPDATE_ADDRESS,
            ReplyIntent::classify('Villa 12, King Fahd Road, Al Olaya District, Riyadh 12212')
        );
    }

    public function test_a_short_unmatched_reply_is_left_for_an_agent(): void
    {
        $this->assertSame(ReplyIntent::UNKNOWN, ReplyIntent::classify('hmm'));
    }
}
