<?php

namespace App\Services\Riders;

use App\Models\ConnectorSetting;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Support\PhoneNumber;

/**
 * The WhatsApp contact riders reach from the app when something goes wrong.
 * Set by the admin on the Riders page; kept with the KSA Express connector's
 * settings.
 */
class RiderSupport
{
    private const PHONE_KEY = 'rider_support_whatsapp';

    private const NAME_KEY = 'rider_support_name';

    /**
     * @return array{whatsapp: string, whatsapp_local: string, name: ?string}|null
     */
    public static function get(): ?array
    {
        $phone = ConnectorSetting::getForConnector(KsaDropExpressDriver::KEY, self::PHONE_KEY);

        if (! $phone) {
            return null;
        }

        return [
            'whatsapp' => $phone,
            'whatsapp_local' => PhoneNumber::local($phone),
            'name' => ConnectorSetting::getForConnector(KsaDropExpressDriver::KEY, self::NAME_KEY) ?: null,
        ];
    }

    /**
     * @param  string|null  $phone  already normalised (see self::normalize()); null clears the contact
     */
    public static function set(?string $phone, ?string $name): void
    {
        ConnectorSetting::setForConnector(KsaDropExpressDriver::KEY, self::PHONE_KEY, $phone);
        ConnectorSetting::setForConnector(KsaDropExpressDriver::KEY, self::NAME_KEY, $phone ? $name : null);
    }

    /**
     * A Saudi or Pakistani mobile in any usual form, or any other number
     * written internationally (+… or 00…), since support may sit elsewhere.
     */
    public static function normalize(?string $input): ?string
    {
        if ($local = PhoneNumber::normalize($input)) {
            return $local;
        }

        $raw = trim((string) $input);
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        } elseif (! str_starts_with($raw, '+')) {
            return null;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+' . $digits : null;
    }
}
