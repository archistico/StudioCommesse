<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Query\CollaboratorEvaluationCriteria;
use App\Service\CollaboratorEvaluationService;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class CollaboratorEvaluationServiceTest extends DatabaseWebTestCase
{
    public function testEvaluationGroupsEntriesByDayAndCalculatesCompleteTotals(): void
    {
        $worker = $this->createUser('riepilogo-giornaliero');
        $otherWorker = $this->createUser('altro-riepilogo');
        $client = $this->createCustomer('Cliente riepilogo giornaliero');
        $project = $this->createProject($client, $worker, 'Commessa riepilogo');
        $activity = $this->createTestActivity($project, $otherWorker, 'Attività condivisa');

        $this->createTestTimeEntry($activity, $worker, '2026-07-03 08:00:00', '2026-07-03 09:30:00', 'Analisi iniziale', true);
        $this->createTestTimeEntry($activity, $worker, '2026-07-03 10:00:00', '2026-07-03 11:00:00', 'Disegno tecnico', false);
        $this->createTestTimeEntry($activity, $worker, '2026-07-04 09:00:00', '2026-07-04 11:00:00', 'Revisione finale', true);
        $this->createTestTimeEntry($activity, $otherWorker, '2026-07-03 12:00:00', '2026-07-03 13:00:00', 'Ora di altra persona');

        /** @var CollaboratorEvaluationService $service */
        $service = self::getContainer()->get(CollaboratorEvaluationService::class);
        $evaluation = $service->build($worker, new CollaboratorEvaluationCriteria(
            userId: $worker->getId() ?? 0,
            periodFrom: new DateTimeImmutable('2026-07-01'),
            periodBefore: new DateTimeImmutable('2026-08-01'),
        ));

        self::assertSame(270, $evaluation->totalMinutes);
        self::assertSame(210, $evaluation->billableMinutes);
        self::assertSame(3, $evaluation->entryCount);
        self::assertSame(1, $evaluation->projectCount);
        self::assertSame(2, $evaluation->workedDayCount);
        self::assertSame(135, $evaluation->averageMinutesPerWorkedDay);
        self::assertCount(2, $evaluation->days);
        self::assertSame('2026-07-04', $evaluation->days[0]->date->format('Y-m-d'));
        self::assertSame(120, $evaluation->days[0]->totalMinutes);
        self::assertSame('2026-07-03', $evaluation->days[1]->date->format('Y-m-d'));
        self::assertSame(150, $evaluation->days[1]->totalMinutes);
        self::assertSame(90, $evaluation->days[1]->billableMinutes);
        self::assertSame('Analisi iniziale', $evaluation->days[1]->entries[0]->description);
        self::assertSame('Disegno tecnico', $evaluation->days[1]->entries[1]->description);
    }
}
