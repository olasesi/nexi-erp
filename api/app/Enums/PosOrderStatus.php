<?php

namespace App\Enums;

enum PosOrderStatus: string
{
    case Completed = 'completed';
    case Held = 'held';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /**
     * The backing values in declaration order, for validation and zero-filled
     * aggregates.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
