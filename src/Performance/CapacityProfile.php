<?php

declare(strict_types=1);

namespace App\Performance;

enum CapacityProfile: int
{
    case Small = 30;
    case Medium = 200;
    case Large = 600;

    public static function fromProjectCount(int $projectCount): self
    {
        return self::tryFrom($projectCount)
            ?? throw new \InvalidArgumentException('Il profilo deve contenere 30, 200 oppure 600 commesse.');
    }

    public function projectCount(): int
    {
        return $this->value;
    }

    public function userCount(): int
    {
        return match ($this) {
            self::Small => 8,
            self::Medium => 16,
            self::Large => 32,
        };
    }

    public function clientCount(): int
    {
        return match ($this) {
            self::Small => 10,
            self::Medium => 40,
            self::Large => 100,
        };
    }

    public function activityCount(): int
    {
        return match ($this) {
            self::Small => 200,
            self::Medium => 1_400,
            self::Large => 4_200,
        };
    }

    public function timeEntryCount(): int
    {
        return $this->activityCount() * 3;
    }

    public function expenseCount(): int
    {
        return $this->projectCount() * 4;
    }

    public function paymentCount(): int
    {
        return $this->projectCount() * 2;
    }

    public function auditCount(): int
    {
        return $this->projectCount() * 10;
    }

    public function attachmentCount(): int
    {
        return intdiv($this->projectCount(), 5);
    }

    public function label(): string
    {
        return sprintf('%d commesse', $this->projectCount());
    }
}
