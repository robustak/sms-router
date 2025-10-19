<?php
declare(strict_types=1);

namespace Robustack\Sms;

use Robustack\Sms\Contracts\SmsSenderContract;
use Robustack\Sms\Exception\SmsException;

/**
 * Central registry and dispatcher for SMS sender implementations.
 *
 * - Register/unregister senders
 * - Resolve default sender from config or first registered
 * - Auto-merge per-sender config from sms.senders.<name>.config into $config passed to send()
 */
class SmsManager
{
    /** @var array<string, SmsSenderContract> */
    private array $senders = [];

    /**
     * Register a sender instance under a unique name.
     */
    public function register(string $name, SmsSenderContract $sender): void
    {
        $this->senders[$name] = $sender;
    }

    /**
     * Remove a previously registered sender.
     */
    public function unregister(string $name): void
    {
        unset($this->senders[$name]);
    }

    /**
     * Determine whether a sender is registered.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->senders);
    }

    /**
     * @return array<string, SmsSenderContract>
     */
    public function all(): array
    {
        return $this->senders;
    }

    /**
     * Send an SMS via a specific registered sender.
     *
     * @throws SmsException when the sender is unknown or the sender reports failure
     */
    public function send(string $name, string $to, string $message, array $config = []): bool
    {
        if (!$this->has($name)) {
            throw new SmsException("SMS sender '{$name}' is not registered.");
        }

        $sender = $this->senders[$name];

        // Auto-inject sender config into config (user-provided overrides file config)
        if (\function_exists('config')) {
            /** @var mixed $senderCfg */
            $senderCfg = \config('sms.senders.' . $name . '.config');
            if (is_array($senderCfg)) {
                $config = array_merge($senderCfg, $config);
            }
        }

        try {
            $result = $sender->send($to, $message, $config);
        } catch (SmsException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SmsException('SMS sending failed: ' . $e->getMessage(), 0, $e);
        }

        if ($result !== true) {
            throw new SmsException("SMS sending via '{$name}' reported failure.");
        }

        return true;
    }

    /**
     * Resolve default sender name from config('sms.default_sender') or first registered sender.
     */
    public function defaultSenderName(): ?string
    {
        $default = null;
        if (\function_exists('config')) {
            /** @var mixed $cfg */
            $cfg = \config('sms.default_sender');
            if (is_string($cfg) && $cfg !== '') {
                $default = $cfg;
            }
        }

        if ($default !== null && $this->has($default)) {
            return $default;
        }

        $keys = array_keys($this->senders);
        return $keys[0] ?? null;
    }
}


