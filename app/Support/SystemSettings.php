<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use Laravel\Pennant\Feature;
use LogicException;

class SystemSettings
{
    public const CONTACT_FIELDS = [
        'data_privacy_contact_name' => 'Name',
        'data_privacy_contact_email' => 'Email',
    ];

    public const ASSESSMENT_FIELDS = [
        'control_status_reassessment_days' => 'Control status reassessment deadline (days)',
    ];

    public static function value(string $name): string
    {
        $value = Feature::store('ledningssystemet')->for(null)->value($name);
        if (! is_string($value)) {
            throw new LogicException("System setting [{$name}] must contain a string.");
        }

        return $value;
    }

    public static function integerValue(string $name): int
    {
        $value = Feature::store('ledningssystemet')->for(null)->value($name);
        if (! is_int($value)) {
            throw new LogicException("System setting [{$name}] must contain an integer.");
        }

        return $value;
    }
}
