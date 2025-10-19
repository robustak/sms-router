# Robustack SMS

Laravel-ready SMS routing with prioritized senders and simple configuration. Register any number of sender classes, route by country or dialing prefix, and the package picks the best available sender with automatic fallback.

## Features

- Pluggable senders via a tiny contract
- Priority-aware routing
  - by_country (ISO alpha-2, requires `giggsey/libphonenumber-for-php`)
  - by_prefix (digits only, longest match wins)
  - default sender, then implicit 'log' fallback
- Central manager for registration and dispatch
- Factory to choose and send via the best sender
- Laravel service provider, facade, publishable config
- Auto-merge per-sender config into `$config` passed to send()

## Installation

```bash
composer require robustack/sms
php artisan vendor:publish --provider="Robustack\\Sms\\Providers\\SmsServiceProvider" --tag=config
```

Optional for better region detection (enables by_country):
```bash
composer require giggsey/libphonenumber-for-php
```

## Configure

`config/sms.php`

```php
return [
    'default_sender' => 'log',

    'senders' => [
        'log' => [
            'class' => \Robustack\Sms\Senders\LogSender::class,
            'config' => [],
        ],
        // 'twilio' => [
        //     'class' => \App\Sms\TwilioSender::class,
        //     'config' => ['from' => env('TWILIO_FROM')],
        // ],
    ],

    'routing' => [
        'default' => 'log',
        'by_country' => [
            'US' => ['twilio', 'log'],
        ],
        'by_prefix' => [
            '1' => ['twilio', 'log'],
            '20' => ['vonage', 'log'],
        ],
    ],
];
```

Notes
- Each sender's `config` is automatically merged into the `$config` argument passed at runtime; runtime values override file config.
- The container resolves sender dependencies; type-hint what you need in the constructor.

## Usage

```php
// Choose and send via the best sender
app(\Robustack\Sms\SmsFactory::class)->send('+14155550100', 'Hello', ['from' => '+11234567890']);

// Facade
\Robustack\Sms\Facades\Sms::send('+201234567890', 'مرحبا');

// Inspect routing decision
$name = \Robustack\Sms\Facades\Sms::chooseSenderName('+201234567890');
```

## Implement a sender

Implement `Robustack\Sms\Contracts\SmsSenderContract`:

```php
namespace App\Sms;

use Robustack\Sms\Contracts\SmsSenderContract;

class TwilioSender implements SmsSenderContract
{
    public function __construct(private \Twilio\Rest\Client $client) {}

    public function send(string $to, string $message, array $config = []): bool
    {
        $from = $config['from'] ?? env('TWILIO_FROM');
        $this->client->messages->create($to, ['from' => $from, 'body' => $message]);
        return true;
    }
}
```

Register in config only (no manual registration needed):
```php
'senders' => [
    'twilio' => [
        'class' => \App\Sms\TwilioSender::class,
        'config' => [
            'from' => env('TWILIO_FROM'),
        ],
    ],
],
```

The package resolves `Twilio\Rest\Client` from the container and merges `senders.twilio.config` into `$config` when calling `send()`.

## Priority and fallback

Order of selection:
1. `by_country` match (if libphonenumber installed)
2. `by_prefix` match (longest match first)
3. `routing.default`
4. Implicit `'log'` sender if registered

If a sender throws or returns false, the next candidate is tried automatically.

## Advanced

- Dynamic registration
```php
app(\Robustack\Sms\SmsManager::class)->register('custom', new CustomSender(...));
```

- Per-request overrides
```php
\Robustack\Sms\Facades\Sms::send('+14155550100', 'Hello', ['from' => '+10987654321']);
```

## Testing

```bash
composer install
composer test
```

## CI

GitHub Actions workflow in `.github/workflows/ci.yml` runs tests on PHP 8.0–8.2.

## Suggested provider SDKs

```bash
composer require twilio/sdk
composer require vonage/client-core vonage/client
```


