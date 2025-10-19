<?php
declare(strict_types=1);

namespace Robustack\Sms\Tests;

use PHPUnit\Framework\TestCase;
use Robustack\Sms\Contracts\SmsSenderContract;
use Robustack\Sms\Exception\SmsException;
use Robustack\Sms\SmsManager;

class ManagerTest extends TestCase
{
    public function testRegisterAndSend(): void
    {
        $manager = new SmsManager();

        $sender = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };

        $manager->register('test', $sender);

        $this->assertTrue($manager->has('test'));
        $this->assertTrue($manager->send('test', '+10000000000', 'hello'));
    }

    public function testUnregister(): void
    {
        $manager = new SmsManager();
        $sender = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };
        $manager->register('test', $sender);
        $manager->unregister('test');
        $this->assertFalse($manager->has('test'));
    }

    public function testUnknownSenderThrows(): void
    {
        $this->expectException(SmsException::class);
        $manager = new SmsManager();
        $manager->send('missing', '+100', 'x');
    }
}


