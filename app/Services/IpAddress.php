<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Adresses IP stockées en VARBINARY(16) (IPv4 et IPv6), comme INET6_ATON().
 */
final class IpAddress
{
    public static function toBinary(string $ip): string
    {
        $binary = filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : false;

        return $binary !== false ? $binary : (string) inet_pton('0.0.0.0');
    }

    public static function fromBinary(?string $binary): ?string
    {
        if ($binary === null || $binary === '') {
            return null;
        }
        $ip = @inet_ntop($binary);

        return $ip !== false ? $ip : null;
    }
}
