<?php
declare(strict_types=1);

namespace Robustack\Sms\Contracts;

use Robustack\Sms\Exception\SmsException;

interface SmsSenderContract
{
    /**
     * Send an SMS message.
     *
     * Implementations should return true on success.
     * On failure, either return false or throw SmsException.
     * Returning false will be converted to SmsException by the caller.
     *
     * @throws SmsException
     */
    public function send(string $to, string $message, array $config = []): bool;
}


