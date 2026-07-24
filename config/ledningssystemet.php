<?php

return [
   'company_name' => env('COMPANY_NAME', 'The Company'),
   'disable_finding' => env('DISABLE_FINDING', false),
   'disable_staff' => env('DISABLE_STAFF', false),
   'disable_supplier' => env('DISABLE_SUPPLIER',false),
   'requirement_source_approval_max_age_days' => (int) env('REQUIREMENT_SOURCE_APPROVAL_MAX_AGE_DAYS', 365),
];
