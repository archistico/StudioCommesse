<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Enum\ActivityStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class ActivityWorkflowRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('saveModes')]
    public function testAutomaticStartIsValidatedBeforeSaving(bool $editing): void
    {
        $user = $this->createUser('date-attivita');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $editing ? $this->createTestActivity($project, $user) : null;
        $activityId = $activity?->getId();
        $this->client->loginUser($user);

        $path = $editing ? '/attivita/'.$activityId.'/modifica' : '/attivita/commessa/'.$project->getId().'/nuova';
        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->selectButton($editing ? 'Salva' : 'Crea attività')->form([
            'activity[title]' => 'Date incoerenti attività',
            'activity[status]' => ActivityStatus::InProgress->value,
            'activity[startAt]' => '',
            'activity[dueAt]' => (new DateTimeImmutable('yesterday 18:00'))->format('Y-m-d\TH:i'),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La scadenza non può precedere l’inizio.');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Activity::class)->findOneBy(['title' => 'Date incoerenti attività']));
        if ($editing) {
            $saved = $this->entityManager->find(Activity::class, $activityId);
            self::assertInstanceOf(Activity::class, $saved);
            self::assertSame(ActivityStatus::NotStarted, $saved->getStatus());
            self::assertNull($saved->getStartAt());
            self::assertNull($saved->getDueAt());
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function saveModes(): iterable
    {
        yield 'creazione' => [false];
        yield 'modifica' => [true];
    }

    #[DataProvider('validStartDates')]
    public function testValidDatesAllowCreationOfInProgressActivities(bool $automatic): void
    {
        $user = $this->createUser('inizio-valido-attivita');
        $project = $this->createProject($this->createCustomer(), $user);
        $before = new DateTimeImmutable();
        $startAt = $automatic ? '' : (new DateTimeImmutable('yesterday 09:00'))->format('Y-m-d\TH:i');
        $dueAt = (new DateTimeImmutable($automatic ? 'tomorrow 18:00' : 'yesterday 18:00'))->format('Y-m-d\TH:i');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/attivita/commessa/'.$project->getId().'/nuova');
        $this->client->submit($crawler->selectButton('Crea attività')->form([
            'activity[title]' => 'Attività con date valide',
            'activity[status]' => ActivityStatus::InProgress->value,
            'activity[startAt]' => $startAt,
            'activity[dueAt]' => $dueAt,
        ]));

        self::assertResponseRedirects('/commesse/'.$project->getId());
        $this->entityManager->clear();
        $saved = $this->entityManager->getRepository(Activity::class)->findOneBy(['title' => 'Attività con date valide']);
        self::assertInstanceOf(Activity::class, $saved);
        self::assertNotNull($saved->getStartAt());
        self::assertSame($dueAt, $saved->getDueAt()?->format('Y-m-d\TH:i'));
        if ($automatic) {
            self::assertGreaterThanOrEqual($before->getTimestamp(), $saved->getStartAt()->getTimestamp());
            self::assertLessThanOrEqual(time(), $saved->getStartAt()->getTimestamp());
        } else {
            self::assertSame($startAt, $saved->getStartAt()->format('Y-m-d\TH:i'));
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function validStartDates(): iterable
    {
        yield 'inizio automatico' => [true];
        yield 'inizio storico esplicito' => [false];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidNumericValuesAreRejectedWithoutOverwritingStoredData(string $field, string $value): void
    {
        $partner = $this->createUser('numeri-attivita', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $partner);
        $activity->setProgressPercent(35)->setHourlyRateOverrideCents(6_000);
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        $form = $crawler->selectButton('Salva')->form();
        // Bypass HTML min/max to exercise server-side validation.
        $values = $form->getPhpValues();
        $values['activity'][$field] = $value;
        $this->client->request('POST', '/attivita/'.$activityId.'/modifica', $values);

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Activity::class, $activityId);
        self::assertInstanceOf(Activity::class, $saved);
        self::assertSame(35, $saved->getProgressPercent());
        self::assertSame(6_000, $saved->getHourlyRateOverrideCents());
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidValues(): iterable
    {
        yield 'avanzamento negativo' => ['progressPercent', '-1'];
        yield 'avanzamento oltre cento' => ['progressPercent', '101'];
        yield 'avanzamento vuoto' => ['progressPercent', ''];
        yield 'tariffa negativa' => ['hourlyRateOverrideCents', '-12,50'];
    }

    #[DataProvider('validNumericValues')]
    public function testValidNumericValuesAreSaved(int $progress, string $rate, ?int $expectedRate): void
    {
        $partner = $this->createUser('valori-validi-attivita', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $partner);
        $activity->setHourlyRateOverrideCents(6_000);
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'activity[progressPercent]' => (string) $progress,
            'activity[hourlyRateOverrideCents]' => $rate,
        ]));

        self::assertResponseRedirects('/commesse/'.$project->getId());
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Activity::class, $activityId);
        self::assertInstanceOf(Activity::class, $saved);
        self::assertSame($progress, $saved->getProgressPercent());
        self::assertSame($expectedRate, $saved->getHourlyRateOverrideCents());
    }

    /** @return iterable<string, array{int, string, ?int}> */
    public static function validNumericValues(): iterable
    {
        yield 'zero e rimozione tariffa specifica' => [0, '', null];
        yield 'novantanove e tariffa in centesimi' => [99, '12,50', 1_250];
    }

    public function testAssigneeFilterWorksWithoutInlineScriptsAndHasASubmitButton(): void
    {
        $user = $this->createUser('filtro-attivita');
        $other = $this->createUser('altro-assegnatario');
        $project = $this->createProject($this->createCustomer(), $user);
        $this->createTestActivity($project, $user, 'Attività propria');
        $this->createTestActivity($project, $other, 'Attività selezionata');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/attivita');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("script-src 'self'", (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertSelectorNotExists('select[onchange]');
        self::assertSelectorExists('select[name="assignee"][data-auto-submit]');
        $this->client->submit($crawler->selectButton('Filtra')->form(['assignee' => (string) $other->getId()]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Attività selezionata');
        self::assertSelectorTextNotContains('table', 'Attività propria');
    }

    #[DataProvider('editPermissions')]
    public function testActivityLinksRespectEditPermissions(string $actor, bool $archived, bool $canEdit): void
    {
        $partner = $this->createUser('socio-link', UserRole::Partner);
        $responsible = $this->createUser('responsabile-link');
        $assignee = $this->createUser('assegnatario-link');
        $author = $this->createUser('autore-link');
        $viewer = $this->createUser('lettore-link');
        $project = $this->createProject($this->createCustomer(), $responsible);
        $activity = $this->createTestActivity($project, $assignee);
        $activity->setCreatedBy($author);
        if ($archived) {
            $project->setStatus(ProjectStatus::Completed);
            $project->archive();
        }
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $projectId = $project->getId();
        $this->client->loginUser(match ($actor) {
            'partner' => $partner,
            'responsible' => $responsible,
            'assignee' => $assignee,
            'author' => $author,
            default => $viewer,
        });

        foreach (['/attivita?assignee='.$assignee->getId(), '/commesse/'.$projectId] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount($canEdit ? 1 : 0, 'a[href="/attivita/'.$activityId.'/modifica"]');
            if (!$canEdit) {
                self::assertSelectorExists('a[href="/ore/attivita/'.$activityId.'"]');
            }
        }

        $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        self::assertResponseStatusCodeSame($canEdit ? 200 : 403);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function editPermissions(): iterable
    {
        yield 'socio' => ['partner', false, true];
        yield 'responsabile' => ['responsible', false, true];
        yield 'assegnatario' => ['assignee', false, true];
        yield 'autore' => ['author', false, true];
        yield 'altro collaboratore' => ['viewer', false, false];
        yield 'socio su archivio' => ['partner', true, false];
    }

    public function testCompletionAndReopeningPreserveWorkflowRules(): void
    {
        $user = $this->createUser('workflow-attivita');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $activity->setStatus(ActivityStatus::InProgress)->setStartAt(new DateTimeImmutable('yesterday 09:00'));
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'activity[status]' => ActivityStatus::Completed->value,
            'activity[progressPercent]' => '35',
            'activity[remainingEstimatedMinutes]' => '120',
        ]));
        self::assertResponseRedirects('/commesse/'.$project->getId());
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Activity::class, $activityId);
        self::assertInstanceOf(Activity::class, $saved);
        self::assertSame(100, $saved->getProgressPercent());
        self::assertSame(0, $saved->getRemainingEstimatedMinutes());
        self::assertNotNull($saved->getCompletedAt());

        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'activity[status]' => ActivityStatus::InProgress->value,
            'activity[progressPercent]' => '100',
            'activity[remainingEstimatedMinutes]' => '120',
        ]));
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Activity::class, $activityId);
        self::assertInstanceOf(Activity::class, $saved);
        self::assertSame(ActivityStatus::InProgress, $saved->getStatus());
        self::assertSame(99, $saved->getProgressPercent());
        self::assertSame(120, $saved->getRemainingEstimatedMinutes());
        self::assertNull($saved->getCompletedAt());
    }
}
