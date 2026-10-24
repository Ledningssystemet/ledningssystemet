<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use Laravel\Pennant\Feature;

class Modules
{
    public const LABELS = [
        'staff' => 'Staff',
        'processes' => 'Processes',
        'finding' => 'Findings',
        'supplier' => 'Suppliers',
        'gdpr' => 'GDPR',
        'archival' => 'Archival',
    ];

    public static function active(string $module): bool
    {
        return Feature::store('ledningssystemet')->for(null)->active($module);
    }

    public static function inactive(string $module): bool
    {
        return ! self::active($module);
    }
}
