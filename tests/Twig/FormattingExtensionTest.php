<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\DurationExtension;
use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;

final class FormattingExtensionTest extends TestCase
{
    public function testDurationUsesUnboundedHoursAndMinutes(): void
    {
        $extension = new DurationExtension();

        self::assertSame('0:00', $extension->formatMinutes(0));
        self::assertSame('2:05', $extension->formatMinutes(125));
        self::assertSame('127:20', $extension->formatMinutes(7640));
        self::assertSame('—', $extension->formatMinutes(null));
    }

    public function testMoneyUsesItalianEuroFormatting(): void
    {
        $extension = new MoneyExtension();

        self::assertSame('€ 1.234,56', $extension->formatCents(123456));
        self::assertSame('€ -20,00', $extension->formatCents(-2000));
        self::assertSame('—', $extension->formatCents(null));
    }
}
