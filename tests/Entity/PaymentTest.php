<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Payment;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testOptionalTextsAreNormalized(): void
    {
        $payment = (new Payment())
            ->setDescription('  Acconto  ')
            ->setReference('  SAL-01  ')
            ->setNotes('   ');

        self::assertSame('Acconto', $payment->getDescription());
        self::assertSame('SAL-01', $payment->getReference());
        self::assertNull($payment->getNotes());
    }
}
