<?php

namespace App\Services\Shipping\Enums;

/**
 * iMile OpenAPI response codes (https://imiledeveloperskit.docs.apiary.io).
 *
 * iMile always returns HTTP 200 for a processed request — the real outcome is
 * the `code` field in the JSON body ("200" = success). Non-200 HTTP statuses
 * only happen for infrastructure failures (e.g. 500 when the server is busy),
 * in which case the body is empty and should be treated as a retryable error.
 */
enum ImileErrorCode: string
{
    case REQUEST_FAILED = '400';
    case INVALID_SIGNATURE = '401';
    case ACCESS_TOKEN_EMPTY = '402';
    case FORBIDDEN = '403';
    case METHOD_NOT_FOUND = '404';
    case DUPLICATE_REQUEST = '405';
    case EMPTY_REQUEST_BODY = '406';
    case INVALID_TOKEN = '407';
    case REQUEST_EXPIRED = '408';
    case CONFIG_ITEM_MISSING = '409';
    case USER_DISABLED = '410';
    case RATE_LIMITED = '412';
    case SERVER_ERROR = '500';
    case SERVER_BUSY = '501';

    case WAYBILL_NOT_EXISTS = '22185';
    case DUPLICATE_CUSTOMER_ORDER_NO = '30001';
    case PICKUP_METHOD_ERROR = '30034';
    case ORIGINAL_WAYBILL_REQUIRED = '30035';
    case SHIPPING_COMPANY_EXISTS = '30036';
    case SHIPPING_COMPANY_NOT_EXISTS = '30037';

    case DATA_LIMIT_EXCEEDED = '40010';
    case PROVIDER_SERVICE_NOT_FOUND = '40011';
    case NO_MATCHING_CITIES = '40012';
    case SUPPLIER_NOT_EXISTS = '40013';
    case PRINT_SHEET_CREATE_FAILED = '40014';
    case LABEL_FILE_READ_FAILED = '40015';
    case SUPPLIER_TRACKING_NO_REQUIRED = '40016';
    case WAYBILL_PUSH_RECORD_NOT_EXISTS = '40017';
    case CUSTOMER_ORDER_NO_NOT_EXISTS = '40018';
    case SENDER_PHONE_INVALID = '40019';
    case SENDER_BACKUP_PHONE_INVALID = '40020';
    case SENDER_CITY_NOT_EXISTS = '40021';
    case SENDER_REGION_NOT_EXISTS = '40022';
    case CONSIGNEE_PHONE_INVALID = '40023';
    case CONSIGNEE_BACKUP_PHONE_INVALID = '40024';
    case CONSIGNEE_CITY_NOT_EXISTS = '40025';
    case CONSIGNEE_AREA_NOT_EXISTS = '40026';
    case CONSIGNEE_AREA_CITY_MISMATCH = '40027';
    case CONSIGNEE_AREA_EMPTY = '40028';
    case WRONG_CARGO_CATEGORY = '40029';
    case WEIGHT_LENGTH_EXCEEDED_11 = '40030';
    case WEIGHT_LENGTH_EXCEEDED_10 = '40031';

    /**
     * Clear, actionable English message for this code.
     */
    public function message(): string
    {
        return match ($this) {
            self::REQUEST_FAILED => 'iMile rejected the request (general failure).',
            self::INVALID_SIGNATURE => 'iMile signature verification failed — check the CustomerID and APIKey.',
            self::ACCESS_TOKEN_EMPTY => 'Access token is empty — the token grant call may have failed.',
            self::FORBIDDEN => 'Forbidden — the customerID does not exist or the signature does not match.',
            self::METHOD_NOT_FOUND => 'iMile does not recognise this request method/endpoint.',
            self::DUPLICATE_REQUEST => 'Duplicate concurrent request — please retry shortly.',
            self::EMPTY_REQUEST_BODY => 'The request body was empty.',
            self::INVALID_TOKEN => 'iMile access token is invalid — it may have expired; a new token will be requested automatically on retry.',
            self::REQUEST_EXPIRED => 'The request expired — check that the timestamp is in milliseconds and consistent with timeZone.',
            self::CONFIG_ITEM_MISSING => 'A required configuration item does not exist on the iMile account.',
            self::USER_DISABLED => 'This iMile customer account does not exist or is disabled.',
            self::RATE_LIMITED => 'iMile rate limit triggered — please retry shortly.',
            self::SERVER_ERROR => 'iMile server error — please retry shortly.',
            self::SERVER_BUSY => 'iMile server is busy — please retry shortly.',

            self::WAYBILL_NOT_EXISTS => 'Waybill number does not exist at iMile.',
            self::DUPLICATE_CUSTOMER_ORDER_NO => 'Duplicate customer order number — this order was already placed at iMile.',
            self::PICKUP_METHOD_ERROR => 'Invalid pickup method.',
            self::ORIGINAL_WAYBILL_REQUIRED => 'The original waybill number cannot be empty for this operation.',
            self::SHIPPING_COMPANY_EXISTS => 'Shipping company already exists.',
            self::SHIPPING_COMPANY_NOT_EXISTS => 'Shipping company does not exist.',

            self::DATA_LIMIT_EXCEEDED => 'The number of requested items exceeds iMile\'s limit (e.g. more than 200 order codes).',
            self::PROVIDER_SERVICE_NOT_FOUND => 'No matching iMile provider service was found for this route.',
            self::NO_MATCHING_CITIES => 'No matching cities were found for the given address.',
            self::SUPPLIER_NOT_EXISTS => 'Supplier does not exist.',
            self::PRINT_SHEET_CREATE_FAILED => 'Failed to create the print sheet (label) file.',
            self::LABEL_FILE_READ_FAILED => 'Failed to read the label file.',
            self::SUPPLIER_TRACKING_NO_REQUIRED => 'Supplier tracking number cannot be empty.',
            self::WAYBILL_PUSH_RECORD_NOT_EXISTS => 'Waybill push record does not exist.',
            self::CUSTOMER_ORDER_NO_NOT_EXISTS => 'Customer order number does not exist at iMile.',
            self::SENDER_PHONE_INVALID => 'Sender phone number must be 6–17 digits (or a valid non-numeric format).',
            self::SENDER_BACKUP_PHONE_INVALID => 'Sender backup phone number must be 6–17 digits.',
            self::SENDER_CITY_NOT_EXISTS => 'Sender city is not recognised by iMile — check the warehouse city.',
            self::SENDER_REGION_NOT_EXISTS => 'Sender region/area is not recognised by iMile — check the warehouse area.',
            self::CONSIGNEE_PHONE_INVALID => 'Receiver phone number must be 6–17 digits (or a valid non-numeric format).',
            self::CONSIGNEE_BACKUP_PHONE_INVALID => 'Receiver backup phone number must be 6–17 digits.',
            self::CONSIGNEE_CITY_NOT_EXISTS => 'Receiver city is not recognised by iMile — use a city from iMile\'s supported list.',
            self::CONSIGNEE_AREA_NOT_EXISTS => 'Receiver area/district is not recognised by iMile.',
            self::CONSIGNEE_AREA_CITY_MISMATCH => 'The receiver area does not belong to the receiver city.',
            self::CONSIGNEE_AREA_EMPTY => 'Receiver area/district cannot be empty for this city.',
            self::WRONG_CARGO_CATEGORY => 'Invalid cargo/goods category.',
            self::WEIGHT_LENGTH_EXCEEDED_11 => 'Weight value is too long (max 11 digits, in kg).',
            self::WEIGHT_LENGTH_EXCEEDED_10 => 'Weight value is too long (max 10 digits, in kg).',
        };
    }

    /**
     * Resolve any iMile code (with the raw message as a fallback) into a clear
     * English message that always includes the code for traceability.
     */
    public static function resolve(string $code, ?string $rawMessage = null): string
    {
        if ($known = self::tryFrom($code)) {
            return $known->message() . ' (code ' . $code . ').';
        }

        $rawMessage = trim((string) $rawMessage);

        if ($rawMessage === '') {
            return 'iMile rejected the request (code ' . $code . ').';
        }

        return 'iMile: ' . $rawMessage . ' (code ' . $code . ').';
    }

    /**
     * Codes worth an automatic single retry with a freshly-fetched token.
     */
    public static function isTokenError(string $code): bool
    {
        return in_array($code, [
            self::ACCESS_TOKEN_EMPTY->value,
            self::INVALID_TOKEN->value,
            self::REQUEST_EXPIRED->value,
        ], true);
    }
}
