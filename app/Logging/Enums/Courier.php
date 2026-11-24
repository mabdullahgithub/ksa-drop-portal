<?php

namespace App\Logging\Enums;

/** Courier integrations, by the slugs CourierManager registers them under. */
enum Courier: int
{
    case JNT_EXPRESS = 1;
    case IMILE = 2;
    case LOGESTECHS = 3;
    case KSADROP_EXPRESS = 4;

    public static function fromSlug(?string $slug): ?self
    {
        return match ($slug) {
            'jnt_express' => self::JNT_EXPRESS,
            'imile' => self::IMILE,
            'logestechs' => self::LOGESTECHS,
            'ksadrop_express' => self::KSADROP_EXPRESS,
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::JNT_EXPRESS => 'J&T Express',
            self::IMILE => 'iMile',
            self::LOGESTECHS => 'LogesTechs (Navix)',
            self::KSADROP_EXPRESS => 'KSA Drop Express (own riders)',
        };
    }
}
