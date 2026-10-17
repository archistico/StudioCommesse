<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\ActivityStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Query\CollaboratorReportCriteria;
use App\Service\CollaboratorReportService;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class CollaboratorReportTest extends DatabaseWebTestCase
{
    public function testReportAndExportsRequirePartnerAndMenuRespectsRole(): void
    {
        $worker = $this->createUser('mario');
        $partner = $this->createUser('socio', UserRole::Partner);
        $paths = ['/report/collaboratori', '/report/collaboratori/'.$worker->getId(), '/report/collaboratori/riepilogo.csv', '/report/collaboratori/ore.csv'];
        foreach ($paths as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('/login');
        }
        $this->client->loginUser($worker);
        foreach ($paths as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', '/dashboard');
        self::assertSelectorNotExists('.navbar a[href="/report/collaboratori"]');
        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/collaboratori');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.navbar a[aria-current="page"]', 'Report collaboratori');
        self::assertSelectorExists('input[name="from"][value="'.(new DateTimeImmutable('monday this week'))->format('Y-m-d').'"]');
        self::assertSelectorExists('input[name="to"][value="'.(new DateTimeImmutable('today'))->format('Y-m-d').'"]');
        self::assertSelectorExists('.report-quick-periods a[aria-current="true"]');
        self::assertSelectorNotExists('details');
        self::assertSelectorTextContains('table', 'Mario');
        self::assertSelectorTextContains('table', 'Nessuna registrazione');
    }

    public function testPersonalHoursCurrentWorkAndArchivedHistoryStayDistinct(): void
    {
        $partner = $this->createUser('socio-report', UserRole::Partner);
        $worker = $this->createUser('mario-report');
        $other = $this->createUser('laura-report');
        $customer = $this->createCustomer('Cliente report');
        $project = $this->createProject($customer, $partner, 'Progetto attivo');
        $activity = $this->createTestActivity($project, $worker, 'Rilievo della sede');
        $activity->setStatus(ActivityStatus::Waiting)->setProgressPercent(40)->setDueAt(new DateTimeImmutable('yesterday'));
        $foreignActivity = $this->createTestActivity($project, $other, 'Relazione condivisa');
        $this->entityManager->flush();
        $this->createTestTimeEntry($activity, $worker, '2026-07-10 08:00', '2026-07-10 10:00', 'Rilievo sul posto');
        $this->createTestTimeEntry($activity, $other, '2026-07-10 10:00', '2026-07-10 11:00', 'Ore di Laura');
        $this->createTestTimeEntry($foreignActivity, $worker, '2026-07-11 08:00', '2026-07-11 09:00', 'Contributo su altra assegnazione', false);
        $this->createTestTimeEntry($activity, $worker, '2026-06-30 23:00', '2026-07-01 01:00', 'Inizio fuori periodo');
        $this->createTestTimeEntry($activity, $worker, '2026-08-01 08:00', '2026-08-01 10:00', 'Dopo il periodo');
        $this->createTestTimeEntry($activity, $worker, '2026-06-29 08:00', null, 'Timer precedente al periodo');
        $archived = $this->createProject($customer, $partner, 'Commessa archiviata', ProjectStatus::Completed);
        $oldActivity = $this->createTestActivity($archived, $worker, 'Lavoro storico');
        $this->createTestTimeEntry($oldActivity, $worker, '2026-07-12 08:00', '2026-07-12 09:30', 'Lavoro della commessa archiviata');
        $oldActivity->setStatus(ActivityStatus::Completed);
        $archived->archive();
        $this->entityManager->flush();
        $this->entityManager->getConnection()->update('activity', ['completed_at' => '2026-07-12 10:00:00'], ['id' => $oldActivity->getId()]);

        $service = self::getContainer()->get(CollaboratorReportService::class);
        $people = $service->build(new CollaboratorReportCriteria(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-07-31'), $worker->getId()));
        self::assertCount(1, $people);
        $person = $people[0];
        self::assertSame(270, $person->minutes);
        self::assertSame(210, $person->billableMinutes);
        self::assertSame(22_500, $person->costCents);
        self::assertCount(1, $person->openActivities);
        self::assertCount(1, $person->completedActivities);
        self::assertCount(1, $person->timers);
        self::assertSame(40, $person->openActivities[0]->progress);
        self::assertSame(420, $person->openActivities[0]->actualMinutes);
        self::assertSame(1, $person->overdueCount());
        self::assertSame(1, $person->waitingCount());
        self::assertSame(1, $person->missingEstimateCount());
        self::assertSame(1, $person->oldTimerCount());
        self::assertCount(3, $person->workGroups());
        self::assertCount(3, $person->days());

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/collaboratori/'.$worker->getId().'?from=2026-07-01&to=2026-07-31');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.report-metrics', '4:30');
        self::assertSelectorTextContains('#situazione', 'Stima incompleta');
        self::assertSelectorTextContains('#registrazioni', 'Contributo su altra assegnazione');
        self::assertSelectorTextContains('#registrazioni', 'Lavoro della commessa archiviata');
        self::assertSelectorTextNotContains('#registrazioni', 'Ore di Laura');
        self::assertSelectorTextNotContains('#registrazioni', 'Inizio fuori periodo');
        self::assertSelectorTextContains('#timer-aperti', '29/06/2026');
        self::assertSelectorTextContains('body', '€ 225,00');
        self::assertSelectorExists('a[href*="ore.csv"][href*="user='.$worker->getId().'"]');
        $this->exportPreview('report-collaboratore');
        $this->client->request('GET', '/report/collaboratori?from=2026-07-01&to=2026-07-31');
        self::assertResponseIsSuccessful();
        $this->exportPreview('report-collaboratori');
    }

    public function testProjectFilterAppliesToHoursCurrentActivitiesTimersAndCsv(): void
    {
        $partner = $this->createUser('socio-filtro-report', UserRole::Partner);
        $worker = $this->createUser('mario-filtro-report', active: false);
        $other = $this->createUser('nessun-lavoro', active: false);
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, $partner, 'Commessa scelta');
        $second = $this->createProject($customer, $partner, 'Commessa esclusa');
        $activity = $this->createTestActivity($project, $worker, 'Lavoro incluso');
        $foreign = $this->createTestActivity($second, $worker, 'Lavoro escluso');
        $this->createTestTimeEntry($activity, $worker, '2026-07-01 09:00', '2026-07-01 10:00', 'Descrizione inclusa');
        $this->createTestTimeEntry($foreign, $worker, '2026-07-02 09:00', '2026-07-02 12:00', 'Descrizione esclusa');
        $this->createTestTimeEntry($foreign, $worker, '2026-07-03 09:00', null, 'Timer escluso');
        $this->client->loginUser($partner);
        $query = '?from=2026-07-01&to=2026-07-31&project='.$project->getId();
        $this->client->request('GET', '/report/collaboratori'.$query);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Mario-filtro-report');
        self::assertSelectorTextNotContains('table', $other->getDisplayName());
        self::assertSelectorTextContains('table', '1:00');
        self::assertSelectorTextNotContains('table', 'Lavoro escluso');
        self::assertSelectorTextNotContains('table', 'Timer aperto');
        $this->client->request('GET', '/report/collaboratori/ore.csv'.$query);
        self::assertResponseIsSuccessful();
        $csv = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Descrizione inclusa', $csv);
        self::assertStringNotContainsString('Descrizione esclusa', $csv);
        self::assertStringNotContainsString('Timer escluso', $csv);
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $this->client->request('GET', '/report/collaboratori/riepilogo.csv'.$query);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mario-filtro-report;01/07/2026;31/07/2026;60;60;50,00;1;', (string) $this->client->getResponse()->getContent());
    }

    public function testInvalidDatesAreExplainedAndNeverSilentlyExported(): void
    {
        $this->client->loginUser($this->createUser('socio-date-report', UserRole::Partner));
        foreach (['from=2026-02-30&to=2026-03-01', 'from=2026-07-31&to=2026-07-01', 'period=custom', 'from=2026-07-01'] as $query) {
            $this->client->request('GET', '/report/collaboratori?'.$query);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[role="alert"]', 'settimana corrente');
            $this->client->request('GET', '/report/collaboratori/ore.csv?'.$query);
            self::assertResponseStatusCodeSame(400);
        }
        foreach (['user[]=1', 'project=abc'] as $query) {
            $this->client->request('GET', '/report/collaboratori?'.$query);
            self::assertResponseStatusCodeSame(400);
        }
    }

    public function testCsvProtectsSpreadsheetFormulasAndExportsAllConcludedEntries(): void
    {
        $partner = $this->createUser('socio-csv-report', UserRole::Partner);
        $worker = $this->createUser('csv-report');
        $worker->setDisplayName('=Nome');
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $worker);
        $this->entityManager->flush();
        for ($i = 0; $i < 30; ++$i) {
            $this->createTestTimeEntry($activity, $worker, '2026-07-10 09:00', '2026-07-10 10:00', '=SUM(A1:A2)');
        }
        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/collaboratori/ore.csv?from=2026-07-01&to=2026-07-31&user='.$worker->getId());
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertSame(30, substr_count($content, "'=SUM(A1:A2)"));
        self::assertStringContainsString("'=Nome", $content);
    }

    private function exportPreview(string $name): void
    {
        if ('1' === getenv('STUDIO_UI_PREVIEW')) {
            $directory = dirname(__DIR__, 2).'/var/ui-preview';
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/'.$name.'.html', (string) $this->client->getResponse()->getContent());
        }
    }
}
