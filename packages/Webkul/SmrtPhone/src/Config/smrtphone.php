<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Optional shared secret for webhook requests
    |--------------------------------------------------------------------------
    |
    | If set, incoming webhooks must send this value in the
    | X-SmrtPhone-Secret header (or ?secret= query string).
    |
    */
    'webhook_secret' => env('SMRTPHONE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Automatically create CRM call activities
    |--------------------------------------------------------------------------
    */
    'create_activities' => (bool) env('SMRTPHONE_CREATE_ACTIVITIES', true),
];
