<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProjectCodeGenerator;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class ProjectCodeGeneratorTest extends DatabaseWebTestCase
{
    public function testGeneratorProducesAnnualProgressiveCodes(): void
    {
        $generator = self::getContainer()->get(ProjectCodeGenerator::class);
        self::assertInstanceOf(ProjectCodeGenerator::class, $generator);

        self::assertSame('2030-001', $generator->nextCode(new DateTimeImmutable('2030-01-05')));
        self::assertSame('2030-002', $generator->nextCode(new DateTimeImmutable('2030-12-31')));
        self::assertSame('2031-001', $generator->nextCode(new DateTimeImmutable('2031-01-01')));
    }
}
