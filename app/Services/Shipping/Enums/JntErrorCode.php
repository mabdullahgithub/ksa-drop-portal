<?php

namespace App\Services\Shipping\Enums;

/**
 * J&T Express API response codes.
 *
 * J&T returns two layers of codes: the documented `145003xxx` business codes and
 * an additional gateway layer (`1450033xxx`) observed in production. Both are
 * modelled here as a single string-backed enum so any code returned by the API
 * can be resolved to a clear, actionable English message.
 *
 * Note: J&T is inconsistent about whether `code` is a string or an integer, so
 * always resolve from a string. The success code is "1" and is handled by the
 * caller before reaching this enum.
 */
enum JntErrorCode: string
{
    case GENERAL_FAILURE = '0';

    // Auth, headers & signature
    case HEADER_SIGNATURE_INVALID = '145003030';
    case BIZ_SIGNATURE_INVALID = '145003031';
    case API_ACCOUNT_EMPTY = '145003071';
    case DIGEST_EMPTY = '145003052';
    case TIMESTAMP_EMPTY = '145003053';

    // System / call exceptions
    case INTERNAL_CALL_EXCEPTION = '145003040';
    case SYSTEM_ERROR = '145005000';
    case ORDER_PLACEMENT_FAILED = '145003041';
    case ORDER_UPDATE_FAILED = '145003203';

    // Parameter validation
    case ILLEGAL_PARAMETERS = '145003050';
    case INVALID_TYPE_SELECTION = '145003087';
    case INVALID_SERVICE_TYPE = '145003200';
    case INCOMPLETE_SERVICE_TIME = '145003088';

    // Region / city / province / address
    case REGION_NOT_ACCEPTED = '145003060';
    case CITY_NOT_ACCEPTED = '145003061';
    case PROVINCE_NOT_ACCEPTED = '145003062';
    case ADDRESS_NOT_MATCHED = '145003064';
    case INCOMPLETE_ADDRESS = '145003086';
    case ADDRESS_TOO_LONG = '145003109';
    case STREET_TOO_LONG = '145003110';

    // Sender / recipient details
    case INCOMPLETE_SENDER = '145003083';
    case INCOMPLETE_RECIPIENT = '145003084';
    case PHONE_EMPTY = '145003085';
    case NAME_INVALID = '145003103';
    case COMPANY_TOO_LONG = '145003104';
    case CONTACT_TOO_LONG = '145003105';
    case POSTCODE_OR_EMAIL_INVALID = '145003106';

    // Item / weight / amount / price
    case WEIGHT_INVALID = '145003092';
    case INCOMPLETE_ITEM_TYPE = '145003093';
    case ITEM_QUANTITY_INVALID = '145003096';
    case AMOUNT_INVALID = '145003099';
    case PRICE_INVALID = '145003107';
    case DESCRIPTION_INVALID = '145003108';
    case PARCEL_COUNT_INVALID = '145003111';

    // COD / payment
    case COD_NOT_ENABLED = '145003112';
    case PAYMENT_METHOD_MISMATCH = '145003113';

    // Duplicates
    case DUPLICATE_ORDER = '145002001';
    case DUPLICATE_CUSTOMER_ORDER_NO = '145003101';

    // Status-transition / cancellation
    case ALREADY_PICKED_UP = '145003201';
    case ALREADY_CANCELLED = '145003202';

    // Gateway-layer variants (a different code layer than the documented codes above)
    case GW_PROVINCE_NOT_ACCEPTED = '1450033315';
    case GW_ABNORMAL_CONTACT_INFO = '1450033326';
    case ADDRESS_DATA_NOT_FOUND = '999002000';

    /**
     * Clear, actionable English message for this code. Address/region/contact
     * codes are phrased to be actionable for the operator; the rest mirror J&T's
     * official documentation.
     */
    public function message(): string
    {
        return match ($this) {
            self::GENERAL_FAILURE => 'J&T Express rejected the request (general failure).',

            self::HEADER_SIGNATURE_INVALID => 'J&T header signature verification failed — check the API account and private key.',
            self::BIZ_SIGNATURE_INVALID => 'J&T business-parameter signature verification failed — check the private key / digest.',
            self::API_ACCOUNT_EMPTY => 'apiAccount is empty — J&T credentials are not configured.',
            self::DIGEST_EMPTY => 'Request digest is empty — the signature could not be generated.',
            self::TIMESTAMP_EMPTY => 'Request timestamp is empty.',

            self::INTERNAL_CALL_EXCEPTION => 'J&T internal call exception — please retry shortly.',
            self::SYSTEM_ERROR => 'J&T system error — please retry shortly.',
            self::ORDER_PLACEMENT_FAILED => 'Order placement failed at J&T — please retry.',
            self::ORDER_UPDATE_FAILED => 'Updating the order failed at J&T — please try again later.',

            self::ILLEGAL_PARAMETERS => 'Illegal parameters were sent to J&T.',
            self::INVALID_TYPE_SELECTION => 'Invalid order type, service type, delivery type, item type, shipment type or settlement method.',
            self::INVALID_SERVICE_TYPE => 'Invalid service type — it must be 01 (Express) or 02 (Standard).',
            self::INCOMPLETE_SERVICE_TIME => 'Incomplete service-time information.',

            self::REGION_NOT_ACCEPTED => 'J&T did not accept the region. Choose a valid KSA region.',
            self::CITY_NOT_ACCEPTED => 'J&T did not accept the city. Use a city recognised by J&T.',
            self::PROVINCE_NOT_ACCEPTED => 'J&T did not accept the province. Choose a valid KSA region.',
            self::ADDRESS_NOT_MATCHED => 'J&T could not match the address — verify the receiver province, city and district.',
            self::INCOMPLETE_ADDRESS => 'Incomplete address information for the shipment.',
            self::ADDRESS_TOO_LONG => 'Too much address information — shorten the address fields.',
            self::STREET_TOO_LONG => 'Street information is too long — shorten the street address.',

            self::INCOMPLETE_SENDER => 'Incomplete sender information — check the warehouse address.',
            self::INCOMPLETE_RECIPIENT => 'Incomplete recipient information — check the receiver address.',
            self::PHONE_EMPTY => 'Phone number cannot be empty.',
            self::NAME_INVALID => 'Name information is invalid.',
            self::COMPANY_TOO_LONG => 'Company information is too long.',
            self::CONTACT_TOO_LONG => 'Contact information is too long.',
            self::POSTCODE_OR_EMAIL_INVALID => 'Postcode or email address is invalid.',

            self::WEIGHT_INVALID => 'The weight value is invalid.',
            self::INCOMPLETE_ITEM_TYPE => 'Incomplete item type.',
            self::ITEM_QUANTITY_INVALID => 'Invalid item quantity.',
            self::AMOUNT_INVALID => 'Invalid amount.',
            self::PRICE_INVALID => 'Price information is invalid.',
            self::DESCRIPTION_INVALID => 'Comments, descriptions or links are invalid.',
            self::PARCEL_COUNT_INVALID => 'The total number of parcels is invalid.',

            self::COD_NOT_ENABLED => 'COD service is not enabled for this account.',
            self::PAYMENT_METHOD_MISMATCH => 'Payment method does not match (expected PP_CASH, CC_CASH or PP_MM).',

            self::DUPLICATE_ORDER => 'Duplicate order — this order has already been placed at J&T.',
            self::DUPLICATE_CUSTOMER_ORDER_NO => 'This customer order number already exists at J&T; cannot place it again.',

            self::ALREADY_PICKED_UP => 'Order is already picked up and can no longer be modified.',
            self::ALREADY_CANCELLED => 'Order is already cancelled and can no longer be modified.',

            self::GW_PROVINCE_NOT_ACCEPTED => 'J&T did not accept the receiver province. Choose a valid KSA region.',
            self::GW_ABNORMAL_CONTACT_INFO => 'J&T did not accept the contact details. Check the sender and receiver '
                . 'name and phone — J&T KSA expects a valid Saudi mobile number (e.g. 05XXXXXXXX or 9665XXXXXXXX) '
                . 'with no extra spaces or symbols.',
            self::ADDRESS_DATA_NOT_FOUND => 'J&T could not match the receiver address. The city or district is not '
                . 'recognised in J&T\'s KSA address list — verify the receiver city and district.',
        };
    }

    /**
     * Resolve any J&T code (with the raw message as a fallback) into a clear
     * English message that always includes the code for traceability.
     */
    public static function resolve(string $code, ?string $rawMessage = null): string
    {
        if ($known = self::tryFrom($code)) {
            return $known->message() . ' (code ' . $code . ').';
        }

        $rawMessage = trim((string) $rawMessage);

        if ($rawMessage === '') {
            return 'J&T Express rejected the request (code ' . $code . ').';
        }

        return 'J&T Express: ' . $rawMessage . ' (code ' . $code . ').';
    }
}
