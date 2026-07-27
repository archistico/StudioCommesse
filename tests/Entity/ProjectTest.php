<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testDefaultsAndCodeAssignment(): void
    {
        $project = new Project();
        $project->assignCode('2026-001');

        self::assertSame('2026-001', $project->getCode());
        self::assertSame(ProjectStatus::NotStarted, $project->getStatus());
        self::assertSame(ProjectPriority::Normal, $project->getPriority());

        $this->expectException(\LogicException::class);
        $project->assignCode('2026-002');
    }

    public function testStatusControlsOperationalDates(): void
    {
        $project = new Project();
        $project->setStatus(ProjectStatus::InProgress);
        self::assertNotNull($project->getStartDate());
        self::assertNull($project->getCompletedAt());

        $project->setStatus(ProjectStatus::Completed);
        $completedAt = $project->getCompletedAt();
        self::assertNotNull($completedAt);
        $project->setStatus(ProjectStatus::Completed);
        self::assertSame($completedAt, $project->getCompletedAt());

        $project->setStatus(ProjectStatus::InProgress);
        self::assertNull($project->getCompletedAt());
    }

    public function testWaitingReasonIsClearedOutsideWaitingState(): void
    {
        $project = (new Project())
            ->setStatus(ProjectStatus::Waiting)
            ->setWaitingReason('Attesa documenti');
        self::assertNull($project->getStartDate());
        $project->normalizeWorkflow();
        self::assertSame('Attesa documenti', $project->getWaitingReason());

        $project->setStatus(ProjectStatus::InProgress);
        $project->normalizeWorkflow();
        self::assertNull($project->getWaitingReason());
    }

    public function testOnlyClosedProjectCanBeArchived(): void
    {
        $project = new Project();

        $this->expectException(\DomainException::class);
        $project->archive();
    }

    public function testRestoreRequiresActiveClientAndResponsible(): void
    {
        $client = (new Client())->setName('Cliente');
        $responsible = (new User())->setDisplayName('Mario')->setUsername('mario')->setActive(true);
        $project = (new Project())
            ->setClient($client)
            ->setResponsible($responsible)
            ->setStatus(ProjectStatus::Completed);
        $project->archive();
        $client->archive();

        $this->expectException(\DomainException::class);
        $project->restore();
    }

    public function testOverdueExcludesClosedAndArchivedProjects(): void
    {
        $project = (new Project())->setDueDate(new DateTimeImmutable('2026-01-01'));
        self::assertTrue($project->isOverdue(new DateTimeImmutable('2026-07-27')));

        $project->setStatus(ProjectStatus::Completed);
        self::assertFalse($project->isOverdue(new DateTimeImmutable('2026-07-27')));
    }
}
