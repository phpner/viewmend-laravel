<?php

declare(strict_types=1);

return [
    'default' => env('VIEWMEND_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'token' => env('VIEWMEND_API_TOKEN'),

            'site_tracker' => [
                'integration_id' => env('VIEWMEND_SITE_TRACKER_INTEGRATION_ID'),
            ],
        ],
    ],
];
