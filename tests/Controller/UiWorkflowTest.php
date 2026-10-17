<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Enum\AttachmentClassification;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class UiWorkflowTest extends DatabaseWebTestCase
{
    public function testDashboardQuickActionsRespectTheRole(): void
    {
        $collaborator = $this->createUser('azioni-collaboratore');
        $partner = $this->createUser('azioni-socio', UserRole::Partner);
        foreach ([$collaborator, $partner] as $user) {
            $this->client->loginUser($user);
            $this->client->request('GET', '/dashboard');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.section-actions a[href="/ore/nuova"][data-action-priority="3"]');
            self::assertSelectorExists('.section-actions a[href="/attivita/nuova"]');
            if ($user === $partner) {
                self::assertSelectorExists('.section-actions a[href="/commesse/nuova"]');
                self::assertSelectorExists('.section-actions a[href="/clienti/nuovo"]');
            } else {
                self::assertSelectorNotExists('.section-actions a[href="/commesse/nuova"]');
                self::assertSelectorNotExists('.section-actions a[href="/clienti/nuovo"]');
            }
        }
    }

    public function testSummaryActionsAndTablesFollowTheSameOrderAcrossSections(): void
    {
        $partner = $this->createUser('ergonomia', UserRole::Partner);
        $customer = $this->createCustomer('Cliente ergonomia');
        $project = $this->createProject($customer, $partner, 'Ristrutturazione e adeguamento della sede operativa – lotto principale');
        $activity = $this->createTestActivity($project, $partner, 'Verifica documentazione tecnica e coordinamento delle attività di progetto');
        $this->createTestTimeEntry($activity, $partner, '2026-10-01 09:00', '2026-10-01 10:00');
        $this->client->loginUser($partner);

        foreach ([
            '/dashboard', '/ore', '/economia', '/controllo', '/audit', '/report/mensile?month=2026-10',
            '/clienti/'.$customer->getId(), '/commesse/'.$project->getId(),
            '/ore/attivita/'.$activity->getId(), '/economia/commessa/'.$project->getId(),
        ] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.page-summary');
            self::assertSelectorExists('.section-actions');
            $html = (string) $this->client->getResponse()->getContent();
            $summary = strpos($html, 'class="page-summary"');
            $actions = strpos($html, 'class="section-actions ');
            $list = strpos($html, '<table ');
            if (false === $list) {
                $list = strpos($html, 'class="list-group ');
            }
            self::assertIsInt($summary, $path);
            self::assertIsInt($actions, $path);
            self::assertIsInt($list, $path);
            self::assertLessThan($actions, $summary, $path);
            self::assertLessThan($list, $actions, $path);
            if ('/audit' === $path) {
                self::assertGreaterThan(1, $crawler->filter('#action option')->count(), 'La barra delle azioni non deve oscurare le opzioni del filtro audit.');
            }
            foreach ($crawler->filter('table') as $table) {
                for ($node = $table->parentNode; $node instanceof \DOMElement; $node = $node->parentNode) {
                    self::assertDoesNotMatchRegularExpression('/\bcol-(?:md|lg|xl)-[1-9]\b/', $node->getAttribute('class'), $path);
                }
            }
            $this->exportPreview($path);
        }

        foreach (['/clienti', '/commesse', '/admin/utenti', '/controllo/collaboratori/'.$partner->getId()] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $this->exportPreview($path);
        }

        foreach ([
            '/clienti/nuovo', '/commesse/nuova', '/admin/utenti/nuovo',
            '/attivita/commessa/'.$project->getId().'/nuova',
            '/ore/attivita/'.$activity->getId().'/nuova',
            '/commesse/'.$project->getId().'/documenti/nuovo',
        ] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.form-actions a.btn-outline-secondary');
            self::assertSelectorExists('.form-actions button.btn-primary[type="submit"]');
            $cancel = $crawler->filter('.form-actions a')->first()->getNode(0);
            $save = $crawler->filter('.form-actions button')->first()->getNode(0);
            self::assertNotNull($cancel);
            self::assertNotNull($save);
            self::assertSame($cancel->parentNode, $save->parentNode);
            $this->exportPreview($path);
        }
    }

    public function testGlobalCreationLinksInferContextAndKeepCollaboratorPermissions(): void
    {
        $user = $this->createUser('creazione-globale');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->createTestTimeEntry($activity, $user, '2026-10-01 09:00', '2026-10-01 10:00');
        $attachment = new Attachment($project, $activity, $user, AttachmentClassification::Technical, 'Relazione_tecnica_e_allegati_di_progetto_versione_definitiva.pdf', 'test/ui.pdf', 'application/pdf', 1024, str_repeat('a', 64));
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        foreach (['/attivita' => '/attivita/nuova', '/documenti' => '/documenti/nuovo', '/ore' => '/ore/nuova'] as $path => $target) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.section-actions a.btn-primary[href="'.$target.'"]');
            $this->exportPreview($path);
        }
        $this->client->request('GET', '/documenti?project='.$project->getId().'&activity='.$activity->getId());
        self::assertSelectorExists('.section-actions a[href="/commesse/'.$project->getId().'/documenti/nuovo?activity='.$activity->getId().'"]');
        $this->client->request('GET', '/documenti?activity='.$activity->getId());
        self::assertSelectorExists('.section-actions a[href="/commesse/'.$project->getId().'/documenti/nuovo?activity='.$activity->getId().'"]');
        $this->client->request('GET', '/ore?activity='.$activity->getId());
        self::assertSelectorExists('.section-actions a[href="/ore/attivita/'.$activity->getId().'/nuova"]');
        $this->client->request('GET', '/clienti');
        self::assertSelectorNotExists('a[href="/clienti/nuovo"]');
        $this->client->request('GET', '/commesse');
        self::assertSelectorNotExists('a[href="/commesse/nuova"]');
        $this->client->request('GET', '/documenti/'.$attachment->getId());
        self::assertResponseIsSuccessful();
        $this->exportPreview('/documenti/dettaglio');
        $this->client->request('GET', '/commesse/'.$project->getId().'/documenti');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[type="file"]');
        $this->exportPreview('/documenti/commessa');
    }

    public function testCreationChoicesExcludeArchivesAndRejectCrossProjectActivities(): void
    {
        $user = $this->createUser('scelta-contesto');
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, $user);
        $other = $this->createProject($customer, $user);
        $archived = $this->createProject($customer, $user, status: ProjectStatus::Completed);
        $archived->archive();
        $activity = $this->createTestActivity($project, $user);
        $foreign = $this->createTestActivity($other, $user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        foreach (['/attivita/nuova', '/documenti/nuovo', '/ore/nuova'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('#creation-project option[value="'.$archived->getId().'"]');
            $this->client->request('GET', $path, ['project' => $archived->getId(), 'continue' => '1']);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('[role="alert"]', 'Seleziona una commessa attiva');
        }
        $this->client->request('GET', '/ore/nuova', ['project' => $project->getId(), 'load' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#creation-activity option[value="'.$activity->getId().'"]');
        self::assertSelectorNotExists('#creation-activity option[value="'.$foreign->getId().'"]');
        $this->exportPreview('/ore/nuova');
        $this->client->request('GET', '/ore/nuova', ['project' => $project->getId(), 'activity' => $foreign->getId(), 'continue' => '1']);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/ore/nuova', ['project' => $project->getId(), 'activity' => $activity->getId(), 'continue' => '1']);
        self::assertResponseRedirects('/ore/attivita/'.$activity->getId().'/nuova');
        foreach (['/attivita/nuova' => '/attivita/commessa/'.$project->getId().'/nuova', '/documenti/nuovo' => '/commesse/'.$project->getId().'/documenti/nuovo'] as $path => $target) {
            $this->client->request('GET', $path, ['project' => $project->getId(), 'continue' => '1']);
            self::assertResponseRedirects($target);
        }
        $this->client->request('GET', '/commesse/'.$archived->getId().'/documenti/nuovo');
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('creationPaths')]
    public function testCreationRequiresAuthentication(string $path): void
    {
        $this->client->request('GET', $path);
        self::assertResponseRedirects('/login');
    }

    /** @return iterable<array{string}> */
    public static function creationPaths(): iterable
    {
        yield ['/attivita/nuova'];
        yield ['/documenti/nuovo'];
        yield ['/ore/nuova'];
        yield ['/economia/nuova-spesa'];
        yield ['/economia/nuovo-incasso'];
    }

    public function testEmptyChoicesExplainHowToProceedWithoutOfferingPartnerActions(): void
    {
        $this->client->loginUser($this->createUser('nessuna-commessa'));
        $this->client->request('GET', '/attivita/nuova');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-page', 'Non ci sono commesse attive');
        self::assertSelectorExists('.form-actions button[disabled]');
        self::assertSelectorNotExists('a[href="/commesse/nuova"]');
    }

    public function testSingleAvailableProjectIsPreselectedWithoutSavingAnything(): void
    {
        $user = $this->createUser('unica-commessa');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->client->loginUser($user);
        $this->client->request('GET', '/ore/nuova');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#creation-project option[selected][value="'.$project->getId().'"]');
        self::assertSelectorExists('#creation-activity option[value="'.$activity->getId().'"]');
        self::assertSame(0, $this->entityManager->getRepository(\App\Entity\TimeEntry::class)->count([]));
    }

    public function testEconomicsCreationKeepsPaymentsReservedToPartners(): void
    {
        $collaborator = $this->createUser('selezione-spese');
        $project = $this->createProject($this->createCustomer(), $collaborator);
        $partner = $this->createUser('selezione-incassi', UserRole::Partner);
        $this->client->loginUser($collaborator);
        $this->client->request('GET', '/economia/nuovo-incasso');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/economia/nuova-spesa', ['project' => $project->getId(), 'continue' => '1']);
        self::assertResponseRedirects('/economia/commessa/'.$project->getId().'/spesa');
        $this->client->loginUser($partner);
        $this->client->request('GET', '/economia');
        self::assertSelectorExists('.section-actions a[href="/economia/nuova-spesa"]');
        self::assertSelectorExists('.section-actions a[href="/economia/nuovo-incasso"]');
        $this->client->request('GET', '/economia/nuovo-incasso');
        self::assertResponseIsSuccessful();
        $this->exportPreview('/economia/nuovo-incasso');
        $this->client->request('GET', '/economia/nuovo-incasso', ['project' => $project->getId(), 'continue' => '1']);
        self::assertResponseRedirects('/economia/commessa/'.$project->getId().'/incasso');
    }

    private function exportPreview(string $path): void
    {
        if ('1' !== getenv('STUDIO_UI_PREVIEW')) {
            return;
        }
        $directory = dirname(__DIR__, 2).'/var/ui-preview';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/'.preg_replace('/[^a-zA-Z0-9-]/', '_', $path).'.html', (string) $this->client->getResponse()->getContent());
    }
}
