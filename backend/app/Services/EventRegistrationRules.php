<?php

namespace App\Services;

final class EventRegistrationRules
{
    public static function allowedModes(string $direction): array
    {
        return match ($direction) {
            'classic' => [
                'single',
                'team',
                'team_classic',
            ],
            'beach' => [
                'single',
                'mixed_group',
                'team_beach',
            ],
            default => ['single'],
        };
    }

    public static function assertModeAllowed(string $direction, string $mode): void
    {
        if (!in_array($mode, self::allowedModes($direction), true)) {
            throw new \DomainException("Недопустимый registration_mode [{$mode}] для direction [{$direction}]");
        }
    }

    public static function groupSize(string $direction, ?string $subtype): int
    {
        if ($direction !== 'beach') {
            return 1;
        }

        return match ($subtype) {
            '2x2' => 2,
            '3x3' => 3,
            '4x4' => 4,
            default => 1,
        };
    }

    public static function requiredPlayersMultiple(string $direction, ?string $subtype): int
    {
        if ($direction !== 'beach') {
            return 0;
        }

        return self::groupSize($direction, $subtype) * 2;
    }
}