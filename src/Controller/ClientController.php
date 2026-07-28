<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use App\Service\ProjectFinancialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clienti')]
#[IsGranted('ROLE_COLLABORATOR')]
final class ClientController extends AbstractController
{
    #[Route('', name: 'app_client_index', methods: ['GET'])]
    public function index(
        Request $request,
        ClientRepository $clientRepository,
        ProjectRepository $projectRepository,
        ProjectFinancialService $financialService,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $includeArchived = $request->query->getBoolean('archiviati');
        $clients = $clientRepository->findFiltered($query, $includeArchived);
        $financialSummariesByClientId = [];
        $user = $this->getUser();

        if ($user instanceof User && $user->isPartner()) {
            $projectSummaries = $financialService->summarizeMany($projectRepository->findForEconomics());
            foreach ($financialService->summarizeByClient($projectSummaries) as $summary) {
                $clientId = $summary->client->getId();
                if (null !== $clientId) {
                    $financialSummariesByClientId[$clientId] = $summary;
                }
            }
        }

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
            'financial_summaries_by_client_id' => $financialSummariesByClientId,
            'query' => $query,
            'include_archived' => $includeArchived,
        ]);
    }

    #[Route('/nuovo', name: 'app_client_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function new(
        Request $request,
        ClientRepository $clientRepository,
        AuditedTransaction $transaction,
    ): Response {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
            $transaction->execute(
                static function () use ($clientRepository, $client): Client {
                    $clientRepository->save($client, false);

                    return $client;
                },
                static fn (Client $saved): AuditRecord => new AuditRecord(
                    AuditAction::ClientCreated,
                    $actorIdentifier,
                    Client::class,
                    $saved->getId(),
                    ['name' => $saved->getName()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', 'Cliente creato correttamente.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nuovo cliente',
            'submit_label' => 'Crea cliente',
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_client_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Client $client, ProjectRepository $projectRepository): Response
    {
        return $this->render('client/show.html.twig', [
            'client' => $client,
            'projects' => $projectRepository->findForClient($client),
        ]);
    }

    #[Route('/{id}/modifica', name: 'app_client_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function edit(
        Client $client,
        Request $request,
        ClientRepository $clientRepository,
        AuditedTransaction $transaction,
    ): Response {
        if ($client->isArchived()) {
            throw $this->createAccessDeniedException('Ripristinare il cliente prima di modificarlo.');
        }

        $previousName = $client->getName();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
            $transaction->execute(
                static function () use ($clientRepository, $client): Client {
                    $clientRepository->save($client, false);

                    return $client;
                },
                static fn (Client $saved): AuditRecord => new AuditRecord(
                    AuditAction::ClientUpdated,
                    $actorIdentifier,
                    Client::class,
                    $saved->getId(),
                    ['previous_name' => $previousName, 'name' => $saved->getName()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', 'Cliente aggiornato correttamente.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        return $this->render('client/form.html.twig', [
            'form' => $form,
            'page_title' => 'Modifica cliente',
            'submit_label' => 'Salva modifiche',
            'client' => $client,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/archivia', name: 'app_client_archive', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function archive(
        Client $client,
        Request $request,
        ClientRepository $clientRepository,
        ProjectRepository $projectRepository,
        AuditedTransaction $transaction,
    ): Response {
        $this->validateCsrf('archive_client_'.$client->getId(), $request);

        $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
        try {
            $transaction->execute(
                static function () use ($client, $clientRepository, $projectRepository): Client {
                    if ($projectRepository->countNonArchivedForClient($client) > 0) {
                        throw new \DomainException('Il cliente ha commesse non archiviate e non può essere archiviato.');
                    }

                    $client->archive();
                    $clientRepository->save($client, false);

                    return $client;
                },
                static fn (Client $saved): AuditRecord => new AuditRecord(
                    AuditAction::ClientArchived,
                    $actorIdentifier,
                    Client::class,
                    $saved->getId(),
                    ['name' => $saved->getName()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', 'Cliente archiviato.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}/ripristina', name: 'app_client_restore', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function restore(
        Client $client,
        Request $request,
        ClientRepository $clientRepository,
        AuditedTransaction $transaction,
    ): Response {
        $this->validateCsrf('restore_client_'.$client->getId(), $request);
        $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
        $transaction->execute(
            static function () use ($client, $clientRepository): Client {
                $client->restore();
                $clientRepository->save($client, false);

                return $client;
            },
            static fn (Client $saved): AuditRecord => new AuditRecord(
                AuditAction::ClientRestored,
                $actorIdentifier,
                Client::class,
                $saved->getId(),
                ['name' => $saved->getName()],
                $request->getClientIp(),
            ),
        );
        $this->addFlash('success', 'Cliente ripristinato.');

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    private function validateCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }
    }

    private function requireCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
