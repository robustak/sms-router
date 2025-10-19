<?php
declare(strict_types=1);

namespace Robustack\Sms;

use libphonenumber\PhoneNumberUtil;
use Robustack\Sms\Exception\SmsException;

/**
 * Routing and selection layer responsible for choosing the best sender.
 *
 * - Supports ISO by-country and by-prefix prioritized rules
 * - Tries senders in order; falls back to default and 'log' if available
 * - Delegates actual sending to SmsManager
 */
class SmsFactory
{
    private SmsManager $manager;
    private ?PhoneNumberUtil $phoneUtil;
    /** @var array{default: (string|null), by_country: array<string,array<int,string>>, by_prefix: array<string,array<int,string>>} */
    private array $routing;

    /**
     * @param array{default?: string, by_country?: array<string,array<int,string>>, by_prefix?: array<string,array<int,string>>} $routing
     */
    public function __construct(SmsManager $manager, ?PhoneNumberUtil $phoneUtil = null, array $routing = [])
    {
        $this->manager = $manager;
        $this->phoneUtil = $phoneUtil;

        if (empty($routing) && \function_exists('config')) {
            /** @var mixed $cfg */
            $cfg = \config('sms.routing');
            if (is_array($cfg)) {
                $routing = $cfg;
            }
        }

        $this->routing = $this->normalizeRouting($routing);
    }

    public function getManager(): SmsManager
    {
        return $this->manager;
    }

    /**
     * Determine the top-priority sender name for a phone number.
     */
    public function chooseSenderName(string $phone): ?string
    {
        $names = $this->chooseSenderNames($phone);
        return $names[0] ?? null;
    }

    /**
     * @return array<int,string>
     */
    /**
     * Build a prioritized list of sender names for a phone number.
     * Returns only registered senders, unique, optionally appending 'log'.
     *
     * @return array<int,string>
     */
    public function chooseSenderNames(string $phone): array
    {
        $candidates = [];

        if ($this->phoneUtil instanceof PhoneNumberUtil) {
            try {
                $parsed = $this->phoneUtil->parse($phone);
                $region = strtoupper((string) $this->phoneUtil->getRegionCodeForNumber($parsed));
                if ($region !== '' && isset($this->routing['by_country'][$region])) {
                    $candidates = array_merge($candidates, $this->routing['by_country'][$region]);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $candidates = array_merge($candidates, $this->getPrefixCandidates($phone));

        $default = $this->routing['default'] ?? null;
        if (is_string($default) && $default !== '') {
            $candidates[] = $default;
        }

        $ordered = $this->filterAndDedupeRegistered($candidates);

        if ($this->manager->has('log') && !in_array('log', $ordered, true)) {
            $ordered[] = 'log';
        }

        return $ordered;
    }

    /**
     * Try sending via the prioritized senders until one succeeds.
     *
     * @throws SmsException if no sender succeeds
     */
    public function send(string $phone, string $message, array $config = []): bool
    {
        $names = $this->chooseSenderNames($phone);
        if (empty($names)) {
            throw new SmsException('No available SMS sender to handle this phone number.');
        }

        $errors = [];
        foreach ($names as $name) {
            try {
                return $this->manager->send($name, $phone, $message, $config);
            } catch (SmsException $e) {
                $errors[] = $name . ': ' . $e->getMessage();
                continue;
            }
        }

        throw new SmsException('All SMS senders failed: ' . implode('; ', $errors));
    }

    /**
     * Normalize routing config into a predictable structure with upper-cased ISO codes
     * and digit-only prefixes, each value as a prioritized array of strings.
     *
     * @param array<string,mixed> $routing
     * @return array{default: (string|null), by_country: array<string,array<int,string>>, by_prefix: array<string,array<int,string>>}
     */
    private function normalizeRouting(array $routing): array
    {
        $byCountry = [];
        if (isset($routing['by_country']) && is_array($routing['by_country'])) {
            foreach ($routing['by_country'] as $countryCode => $names) {
                $code = strtoupper((string) $countryCode);
                $byCountry[$code] = array_values(array_map('strval', is_array($names) ? $names : [$names]));
            }
        }

        $byPrefix = [];
        if (isset($routing['by_prefix']) && is_array($routing['by_prefix'])) {
            foreach ($routing['by_prefix'] as $prefix => $names) {
                $key = ltrim((string) $prefix, '+');
                $byPrefix[$key] = array_values(array_map('strval', is_array($names) ? $names : [$names]));
            }
        }

        return [
            'default' => isset($routing['default']) && is_string($routing['default']) ? $routing['default'] : null,
            'by_country' => $byCountry,
            'by_prefix' => $byPrefix,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function getPrefixCandidates(string $phone): array
    {
        $digits = ltrim($phone, '+');
        $byPrefix = $this->routing['by_prefix'] ?? [];
        if (empty($byPrefix)) {
            return [];
        }

        uksort($byPrefix, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });

        foreach ($byPrefix as $prefix => $names) {
            $prefixStr = (string) $prefix;
            if (str_starts_with($digits, $prefixStr)) {
                return $names;
            }
        }

        return [];
    }

    /**
     * @param array<int,string> $candidateNames
     * @return array<int,string>
     */
    private function filterAndDedupeRegistered(array $candidateNames): array
    {
        $seen = [];
        $result = [];
        foreach ($candidateNames as $name) {
            $name = (string) $name;
            if ($name === '' || isset($seen[$name]) || !$this->manager->has($name)) {
                continue;
            }
            $seen[$name] = true;
            $result[] = $name;
        }

        // Append 'log' fallback if present and not already included
        if ($this->manager->has('log') && !isset($seen['log'])) {
            $result[] = 'log';
        }

        return $result;
    }
}


