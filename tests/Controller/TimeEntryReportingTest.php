<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class TimeEntryReportingTest extends DatabaseWebTestCase
{
    public function testProjectShowsConsolidatedTotalAndBreakdownByPerson(): void
    {
        $assignee = $this->createUser('assegnatario');
        $contributor = $this->createUser('contributore');
        $viewer = $this->createUser('osservatore');
        $project = $this->createProject($this->createCustomer('Cliente Ore'), $assignee, 'Commessa multipersona');
        $activity = $this->createTestActivity($project, $assignee, 'Rilievo condiviso');
        $this->createTestTimeEntry($activity, $assignee, '2026-07-20 09:00:00', '2026-07-20 10:30:00');
        $this->createTestTimeEntry($activity, $contributor, '2026-07-20 11:00:00', '2026-07-20 13:00:00');
        $this->createTestTimeEntry($activity, $viewer, '2026-07-20 14:00:00', null, 'Timer non consolidato');
        $this->client->loginUser($viewer);

        $this->client->request('GET', '/commesse/'.$project->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Consuntivato totale: 3:30');
        self::assertSelectorTextContains('body', 'Assegnatario');
        self::assertSelectorTextContains('body', '1:30');
        self::assertSelectorTextContains('body', 'Contributore');
        self::assertSelectorTextContains('body', '2:00');
        self::assertStringNotContainsString('Timer non consolidato', (string) $this->client->getResponse()->getContent());
    }

    public function testActivityFilterUsesAssigneeRatherThanTimeEntryAuthor(): void
    {
        $maria = $this->createUser('maria');
        $luca = $this->createUser('luca');
        $project = $this->createProject($this->createCustomer('Cliente Assegnazioni'), $maria);
        $assignedToMaria = $this->createTestActivity($project, $maria, 'Attività assegnata a Maria');
        $assignedToLuca = $this->createTestActivity($project, $luca, 'Attività assegnata a Luca');
        $this->createTestTimeEntry($assignedToLuca, $maria, '2026-07-21 09:00:00', '2026-07-21 10:00:00');
        $this->client->loginUser($maria);

        $this->client->request('GET', '/attivita?assignee='.$maria->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('label[for="assignee"]', 'Assegnatario');
        self::assertSelectorTextContains('.filter-explanation', 'persona responsabile');
        self::assertSelectorTextContains('table', $assignedToMaria->getTitle());
        self::assertStringNotContainsString($assignedToLuca->getTitle(), (string) $this->client->getResponse()->getContent());
    }

    public function testActivityMineFilterAcceptsTheExplicitMeValue(): void
    {
        $maria = $this->createUser('maria-me');
        $project = $this->createProject($this->createCustomer('Cliente Me'), $maria);
        $activity = $this->createTestActivity($project, $maria, 'Attività personale');
        $this->client->loginUser($maria);

        $this->client->request('GET', '/attivita?assignee=me');

        self::assertResponseIsSuccessful();
        $form = $this->client->getCrawler()->selectButton('Mostra')->form();
        self::assertSame('me', $form->get('assignee')->getValue());
        self::assertSelectorTextContains('table', $activity->getTitle());
    }

    public function testGlobalHoursReportFiltersByActualWorkerIndependentlyFromAssignee(): void
    {
        $partner = $this->createUser('socio-report', UserRole::Partner);
        $assignee = $this->createUser('responsabile-report');
        $worker = $this->createUser('autrice-report');
        $client = $this->createCustomer('Cliente Report');
        $firstProject = $this->createProject($client, $assignee, 'Commessa inclusa');
        $secondProject = $this->createProject($client, $assignee, 'Commessa esclusa');
        $firstActivity = $this->createTestActivity($firstProject, $assignee, 'Attività condivisa');
        $secondActivity = $this->createTestActivity($secondProject, $worker, 'Attività fuori filtro');
        $this->createTestTimeEntry(
            $firstActivity,
            $worker,
            '2026-07-22 09:00:00',
            '2026-07-22 11:15:00',
            'Contributo trasversale',
            false,
        );
        $this->createTestTimeEntry(
            $secondActivity,
            $worker,
            '2026-07-23 09:00:00',
            '2026-07-23 10:00:00',
            'Fuori filtro',
            false,
        );
        $this->client->loginUser($partner);

        $this->client->request('GET', sprintf(
            '/ore?project=%d&user=%d&from=2026-07-22&to=2026-07-22&billable=0',
            $firstProject->getId(),
            $worker->getId(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Ore');
        self::assertSelectorTextContains('body', '2:15');
        self::assertSelectorTextContains('table', 'Contributo trasversale');
        self::assertSelectorTextContains('table', 'Autrice-report');
        self::assertSelectorTextContains('table', 'Assegnatario: Responsabile-report');
        self::assertStringNotContainsString('Fuori filtro', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Tariffa', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Costo', (string) $this->client->getResponse()->getContent());
    }

    public function testCollaboratorCanOpenGlobalHoursArea(): void
    {
        $collaborator = $this->createUser('lettore-ore');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/ore');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Ore');
        self::assertSelectorTextContains('a.nav-link[href="/ore"]', 'Ore');
    }

    public function testGlobalHoursReportAcceptsEmptySelectFilters(): void
    {
        $collaborator = $this->createUser('ore-filtri-vuoti');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/ore?project=&activity=&user=&billable=&from=2026-07-08&to=2026-07-27');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Ore');
    }

}
