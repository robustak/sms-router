<?php
declare(strict_types=1);

namespace Robustack\Sms\Facades;

use Illuminate\Support\Facades\Facade;
use Robustack\Sms\SmsFactory;

/**
 * @method static string|null chooseSenderName(string $phone)
 * @method static bool send(string $phone, string $message, array $config = [])
 * @method static bool sendBest(string $phone, string $message, array $config = [])
 * @method static \Robustack\Sms\SmsManager getManager()
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsFactory::class;
    }
}


