<?php
declare(strict_types=1);

namespace Robustack\Sms\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Robustack\Sms\Senders\LogSender;

class SendersTest extends TestCase
{
    public function testLogSenderReturnsTrue(): void
    {
        $logger = new class implements LoggerInterface {
            public function emergency($message, array $context = array()) {}
            public function alert($message, array $context = array()) {}
            public function critical($message, array $context = array()) {}
            public function error($message, array $context = array()) {}
            public function warning($message, array $context = array()) {}
            public function notice($message, array $context = array()) {}
            public function info($message, array $context = array()) {}
            public function debug($message, array $context = array()) {}
            public function log($level, $message, array $context = array()) {}
        };

        $sender = new LogSender($logger);
        $this->assertTrue($sender->send('+100', 'Hello'));
    }
}


