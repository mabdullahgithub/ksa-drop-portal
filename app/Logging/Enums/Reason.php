<?php

namespace App\Logging\Enums;

/**
 * Why something failed or was refused, shared by every domain. Written to the
 * log as the number only (context key `rsn`). Append-only: never renumber,
 * never reuse a number. Blocks of 1000: 1xxx rejected input, 2xxx transport,
 * 3xxx data we hold, 4xxx configuration, 9xxx catch-alls.
 */
enum Reason: int
{
    // 1xxx — the other side sent something we refuse
    case SIGNATURE_INVALID = 1001;
    case PAYLOAD_FIELD_MISSING = 1002;
    case SHOP_MISMATCH = 1003;
    case PAYLOAD_MALFORMED = 1004;
    case CREDENTIALS_INVALID = 1005;
    case UNKNOWN_RECORD = 1006;
    case DUPLICATE_DELIVERY = 1007;
    case VERIFY_TOKEN_INVALID = 1008;
    case PARTNER_CODE_UNKNOWN = 1009;
    case INVALID_SHOP_DOMAIN = 1010;
    case STATE_INVALID = 1011;

    // 2xxx — talking to a third party failed
    case RATE_LIMITED = 2001;
    case TIMEOUT = 2002;
    case HTTP_5XX = 2003;
    case HTTP_4XX = 2004;
    case CONNECTION_FAILED = 2005;
    case TOKEN_REJECTED = 2006;
    case VENDOR_REJECTED = 2007;
    case RESPONSE_UNREADABLE = 2008;

    // 3xxx — the data we hold is not usable
    case PHONE_UNUSABLE = 3001;
    case ADDRESS_UNUSABLE = 3002;
    case RECORD_MISSING = 3003;
    case STATE_NOT_ALLOWED = 3004;
    case NOTHING_TO_DO = 3005;
    case NO_LINKED_CLIENT = 3006;

    // 4xxx — configuration
    case NOT_CONFIGURED = 4001;
    case DISABLED = 4002;
    case TOKEN_MISSING = 4003;

    // 9xxx — catch-alls
    case VENDOR_UNMAPPED = 9998;
    case UNKNOWN = 9999;

    public function description(): string
    {
        return match ($this) {
            self::SIGNATURE_INVALID => 'Signature or HMAC did not verify',
            self::PAYLOAD_FIELD_MISSING => 'A required field is missing from the payload',
            self::SHOP_MISMATCH => 'Shop domain does not match the connection',
            self::PAYLOAD_MALFORMED => 'Payload is not valid or not the expected shape',
            self::CREDENTIALS_INVALID => 'Credentials sent with the request are wrong',
            self::UNKNOWN_RECORD => 'Payload refers to a record we do not have',
            self::DUPLICATE_DELIVERY => 'Same delivery already processed',
            self::VERIFY_TOKEN_INVALID => 'Webhook verify token did not match',
            self::PARTNER_CODE_UNKNOWN => 'Partner/customer code is not one we accept',
            self::INVALID_SHOP_DOMAIN => 'Shop domain is missing or not a valid myshopify.com domain',
            self::STATE_INVALID => 'OAuth state missing, expired or not minted by us',
            self::RATE_LIMITED => 'Third party rate limit reached',
            self::TIMEOUT => 'Third party did not answer in time',
            self::HTTP_5XX => 'Third party answered with a server error',
            self::HTTP_4XX => 'Third party refused the request (client error)',
            self::CONNECTION_FAILED => 'Could not connect to the third party',
            self::TOKEN_REJECTED => 'Third party rejected our access token',
            self::VENDOR_REJECTED => 'Third party rejected the request; see vendor code',
            self::RESPONSE_UNREADABLE => 'Third party response could not be read',
            self::PHONE_UNUSABLE => 'Phone number is missing or cannot be normalised',
            self::ADDRESS_UNUSABLE => 'Address is missing or cannot be resolved',
            self::RECORD_MISSING => 'A record we expected to find does not exist',
            self::STATE_NOT_ALLOWED => 'Record is in a state that does not allow this action',
            self::NOTHING_TO_DO => 'Nothing to process',
            self::NO_LINKED_CLIENT => 'No client account is linked to the signed-in user',
            self::NOT_CONFIGURED => 'Integration is not configured',
            self::DISABLED => 'Feature or integration is switched off',
            self::TOKEN_MISSING => 'No access token stored for this connection',
            self::VENDOR_UNMAPPED => 'Vendor error code that has no mapping yet',
            self::UNKNOWN => 'Unknown cause',
        };
    }
}
