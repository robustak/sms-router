<?php
declare(strict_types=1);

namespace Robustack\Sms\Providers;

use Illuminate\Support\ServiceProvider;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;
use Robustack\Sms\SmsFactory;
use Robustack\Sms\SmsManager;
use Robustack\Sms\Senders\LogSender;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sms.php', 'sms');

        $this->app->singleton(SmsManager::class, function ($app) {
            $manager = new SmsManager();

            // Register senders from config
            $senders = (array) config('sms.senders', []);
            foreach ($senders as $name => $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $class = $definition['class'] ?? null;
                if (!is_string($class) || $class === '') {
                    continue;
                }
                try {
                    $parameters = [];
                    $cfg = null;
                    if (array_key_exists('config', $definition) && is_array($definition['config'])) {
                        $cfg = $definition['config'];
                        $parameters['config'] = $cfg; // constructor named parameter support
                    }
                    $instance = $app->make($class, $parameters);

                    // If the instance exposes a configuration setter or property, pass the config
                    if (is_array($cfg)) {
                        if (method_exists($instance, 'setConfig')) {
                            $instance->setConfig($cfg);
                        } elseif (method_exists($instance, 'setOptions')) {
                            $instance->setOptions($cfg);
                        } elseif (property_exists($instance, 'config')) {
                            $instance->config = $cfg;
                        }
                    }
                    if ($instance instanceof \Robustack\Sms\Contracts\SmsSenderContract) {
                        $manager->register((string) $name, $instance);
                    }
                } catch (\Throwable $e) {
                    // Skip invalid sender definitions silently
                }
            }

            // Ensure 'log' sender is available as last-resort fallback
            if (!$manager->has('log')) {
                /** @var LoggerInterface $logger */
                $logger = $app->make(LoggerInterface::class);
                $manager->register('log', new LogSender($logger));
            }

            return $manager;
        });

        // Bind concrete SmsFactory and alias legacy key Robustack\Sms\Factory
        $this->app->singleton(SmsFactory::class, function ($app) {
            /** @var SmsManager $manager */
            $manager = $app->make(SmsManager::class);

            $phoneUtil = null;
            if (class_exists(PhoneNumberUtil::class)) {
                $phoneUtil = PhoneNumberUtil::getInstance();
            }

            /** @var array $routing */
            $routing = (array) config('sms.routing', []);

            return new SmsFactory($manager, $phoneUtil, $routing);
        });

        // Backwards-compatible alias so app(\Robustack\Sms\Factory::class) resolves the same singleton
        $this->app->alias(SmsFactory::class, \Robustack\Sms\Factory::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/sms.php' => config_path('sms.php'),
        ], 'config');
    }
}


