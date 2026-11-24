<?php

namespace App\Logging\Enums;

/** How a context value is validated before it may be written. */
enum CtxType: string
{
    case INT_ID = 'int_id';             // positive integer primary key
    case ENUM = 'enum';                 // backing value of the key's enum
    case EXTERNAL_ID = 'external_id';   // vendor identifier: <=128 chars of [A-Za-z0-9_-./:#], never prose
    case COUNT = 'count';               // non-negative integer
    case DURATION_MS = 'duration_ms';   // non-negative integer, milliseconds
    case AMOUNT = 'amount';             // non-negative integer, halalas (1/100 SAR)
    case ATTEMPT = 'attempt';           // non-negative integer
    case HTTP_STATUS = 'http_status';   // integer 100-599
    case BOOL = 'bool';                 // written as 1 or 0

    public function description(): string
    {
        return match ($this) {
            self::INT_ID => 'Positive integer primary key',
            self::ENUM => 'Backing value of a PHP enum, decoded by the key\'s enum',
            self::EXTERNAL_ID => 'Vendor identifier, up to 128 chars of letters, digits and _-./:#',
            self::COUNT => 'Non-negative integer',
            self::DURATION_MS => 'Non-negative integer, milliseconds',
            self::AMOUNT => 'Non-negative integer, halalas',
            self::ATTEMPT => 'Non-negative integer, attempt number',
            self::HTTP_STATUS => 'HTTP status code 100-599',
            self::BOOL => '1 or 0',
        };
    }
}
