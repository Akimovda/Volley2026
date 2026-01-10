<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Account linking by code
    |--------------------------------------------------------------------------
    | ENV: ACCOUNT_LINK_BY_CODE=true|false
    */
    'link_by_code' => (bool) env('ACCOUNT_LINK_BY_CODE', false),
];
