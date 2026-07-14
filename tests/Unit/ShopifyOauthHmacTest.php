<?php

namespace Tests\Unit;

use App\Services\ShopifyService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Shopify signs the callback query string as it appears on the wire. The `host`
 * param is base64 of "admin.shopify.com/store/{handle}", so it carries "="
 * padding unless the handle's length is a multiple of 3 — and that padding
 * reaches us percent-encoded as "%3D".
 *
 * Verifying against Laravel's decoded params silently turned "%3D" back into
 * "=", so the signed bytes differed and the callback 403'd for every store whose
 * handle length was not a multiple of 3 — roughly two stores in three.
 */
class ShopifyOauthHmacTest extends TestCase
{
    private const SECRET = 'shpss_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.shopify.secret', self::SECRET);
    }

    /**
     * Build the callback exactly as Shopify sends it: sorted, percent-encoded
     * query string, signed over those raw bytes.
     *
     * @return array{0: array<string,string>, 1: string} decoded params, raw query string
     */
    private function shopifyCallback(string $handle): array
    {
        $params = [
            'code'      => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
            'host'      => base64_encode("admin.shopify.com/store/{$handle}"),
            'shop'      => "{$handle}.myshopify.com",
            'state'     => '0123456789abcdef0123456789abcdef',
            'timestamp' => '1752566400',
        ];

        ksort($params);

        // http_build_query percent-encodes, which is what lands in the address bar.
        $signed = http_build_query($params);

        $params['hmac'] = hash_hmac('sha256', $signed, self::SECRET);

        return [$params, $signed.'&hmac='.$params['hmac']];
    }

    public static function handleProvider(): array
    {
        return [
            // strlen % 3 == 0 -> no "=" padding. The only case that ever worked.
            'unpadded host'         => ['abc'],
            'unpadded host, longer' => ['ksadrop-store-x'],
            // strlen % 3 != 0 -> "=" / "==" padding, encoded as %3D on the wire.
            'single-pad host'       => ['reviewer-store'],
            'double-pad host'       => ['my-test-store'],
            // The store the app reviewer actually failed to connect (len 10).
            'reported failing store' => ['luciferxyz'],
        ];
    }

    #[DataProvider('handleProvider')]
    public function test_it_verifies_the_callback_for_any_store_handle(string $handle): void
    {
        [$params, $rawQuery] = $this->shopifyCallback($handle);

        $this->assertTrue(
            app(ShopifyService::class)->verifyOauthHmac($params, $rawQuery),
            "HMAC verification failed for store handle [{$handle}]."
        );
    }

    public function test_it_still_rejects_a_forged_callback(): void
    {
        [$params, $rawQuery] = $this->shopifyCallback('reviewer-store');

        $params['shop'] = 'attacker.myshopify.com';
        $rawQuery = str_replace('reviewer-store.myshopify.com', 'attacker.myshopify.com', $rawQuery);

        $this->assertFalse(
            app(ShopifyService::class)->verifyOauthHmac($params, $rawQuery),
            'A tampered callback must not verify.'
        );
    }

    public function test_it_rejects_a_callback_with_no_hmac(): void
    {
        $this->assertFalse(
            app(ShopifyService::class)->verifyOauthHmac(['shop' => 'x.myshopify.com'], 'shop=x.myshopify.com')
        );
    }
}
