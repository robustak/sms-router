<?php
declare(strict_types=1);

namespace Robustack\Sms\Senders;

use Psr\Log\LoggerInterface;
use Robustack\Sms\Contracts\SmsSenderContract;
use Robustack\Sms\Exception\SmsException;

class LogSender implements SmsSenderContract
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function send(string $to, string $message, array $config = []): bool
    {
        try {
            $context = ['to' => $to, 'config' => $config];
            $this->logger->info('[SMS] ' . $message, $context);
            return true;
        } catch (\Throwable $e) {
            throw new SmsException('Logging SMS failed: ' . $e->getMessage(), 0, $e);
        }
    }
}


