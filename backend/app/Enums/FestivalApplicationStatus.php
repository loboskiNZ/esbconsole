<?php

namespace App\Enums;

enum FestivalApplicationStatus: string
{
    case NotApplied = 'not_applied';
    case Applied = 'applied';
    case UnderReview = 'under_review';
    case Waitlisted = 'waitlisted';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotApplied => 'Not Applied',
            self::Applied => 'Applied',
            self::UnderReview => 'Under Review',
            self::Waitlisted => 'Waitlisted',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function normalize(?string $value): self
    {
        $value = mb_strtolower(trim($value ?? ''));

        if ($value === '') {
            return self::NotApplied;
        }

        return self::tryFrom($value) ?? self::NotApplied;
    }
}
