<?php

declare(strict_types=1);

/**
 * m4p_packaging
 *
 * @author    Modules4Presta <contact@modules4presta.io>
 * @copyright 2026 Nice Code sp. z o.o. (Modules4Presta)
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Formats an ICU plural pattern returned by trans(); the Symfony pipe syntax picks no plural rule for "pl-PL".
 */
class M4pPackagingPlural
{
    public static function format(string $pattern, int $count, string $locale): string
    {
        $formatted = MessageFormatter::formatMessage($locale, $pattern, ['count' => $count]);

        return $formatted === false ? (string) $count : $formatted;
    }
}
