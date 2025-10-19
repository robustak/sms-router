<?php
declare(strict_types=1);

namespace Robustack\Sms\Tests;

use PHPUnit\Framework\TestCase;
use Robustack\Sms\Contracts\SmsSenderContract;
use Robustack\Sms\Exception\SmsException;
use Robustack\Sms\SmsFactory;
use Robustack\Sms\SmsManager;

class FactoryTest extends TestCase
{
    private function makeSender(): SmsSenderContract
    {
        return new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };
    }

    public function testChooseSenderByPrefixAndDefault(): void
    {
        $manager = new SmsManager();
        $twilio = $this->makeSender();
        $vonage = $this->makeSender();
        $manager->register('twilio', $twilio);
        $manager->register('vonage', $vonage);

        $routing = [
            'default' => 'twilio',
            'by_prefix' => [
                '20' => ['vonage', 'twilio'],
                '1' => ['twilio', 'vonage'],
            ],
        ];

        $factory = new SmsFactory($manager, null, $routing);

        $this->assertSame('twilio', $factory->chooseSenderName('+14155550100'));
        $this->assertSame('vonage', $factory->chooseSenderName('+201234567890'));
        $this->assertSame('twilio', $factory->chooseSenderName('+999')); // fallback default
    }

    public function testPrioritizedFallbackOnFailure(): void
    {
        $manager = new SmsManager();
        $failing = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                throw new SmsException('failed');
            }
        };
        $working = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };
        $manager->register('first', $failing);
        $manager->register('second', $working);
        $manager->register('log', $working);

        $routing = [
            'default' => 'first',
            'by_prefix' => [
                '1' => ['first', 'second'],
            ],
        ];
        $factory = new SmsFactory($manager, null, $routing);

        $this->assertTrue($factory->send('+1555', 'Hello'));
    }

    public function testAllFailFallsBackToLog(): void
    {
        $manager = new SmsManager();
        $failing = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                throw new SmsException('failed');
            }
        };
        $log = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };
        $manager->register('a', $failing);
        $manager->register('b', $failing);
        $manager->register('log', $log);

        $routing = [
            'default' => 'a',
            'by_prefix' => [
                '9' => ['a', 'b'],
            ],
        ];
        $factory = new SmsFactory($manager, null, $routing);

        $this->assertTrue($factory->send('+9', 'Hello'));
    }

    public function testSendBestDelegates(): void
    {
        $manager = new SmsManager();
        $sender = new class implements SmsSenderContract {
            public function send(string $to, string $message, array $config = []): bool
            {
                return true;
            }
        };
        $manager->register('twilio', $sender);
        $factory = new SmsFactory($manager, null, ['default' => 'twilio']);

        $this->assertTrue($factory->send('+100', 'Hello'));
    }

    public function testSendBestThrowsWhenNoSender(): void
    {
        $this->expectException(SmsException::class);
        $manager = new SmsManager();
        $factory = new SmsFactory($manager, null, []);
        $factory->send('+100', 'Hello');
    }
}


