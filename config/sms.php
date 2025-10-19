<?php
declare(strict_types=1);

return [
    // The default sender name to use when routing doesn't match.
    'default_sender' => env('SMS_DEFAULT_SENDER', 'log'),

    'senders' => [
        'log' => [
            'class' => \Robustack\Sms\Senders\LogSender::class,
            'config' => [],
        ],
    ],

    // Routing configuration
    'routing' => [
        // When no rule matches, fall back to this sender name
        'default' => env('SMS_ROUTING_DEFAULT', 'log'),

        // Map ISO alpha-2 region to sender name (requires libphonenumber)
        // Examples:
        // 'US' => 'twilio',
        // 'EG' => 'vonage',
        'by_country' => [
            'US' => ['twilio', 'nexmo'],
            'eg' => ['nexmo', 'twilio'],
        ],

        // Fallback: naive prefix matching, like "+1" or "+20" (longest match wins)
        // Examples:
        // '+1' => 'twilio',
        // '+20' => 'vonage',
        'by_prefix' => [
            '1' => ['twilio', 'nexmo'],
            '20' => ['nexmo', 'twilio'],
        ],
    ],
];


