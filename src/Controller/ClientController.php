<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Service\AuditLogger;
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
    public function index(Request $request, ClientRepository $clientRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $includeArchived = $request->query->getBoolean('archiviati');

        return $this->render('client/index.html.twig', [
            'clients' => $clientRepository->findFiltered($query, $includeArchived),
            'query' => $query,
            'include_archived' => $includeArchived,
        ]);
    }

    #[Route('/nuovo', name: 'app_client_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function new(
        Request $request,
        ClientRepository $clientRepository,
        AuditLogger $auditLogger,
    ): Response {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clientRepository->save($client, true);
            $auditLogger->log(
                AuditAction::ClientCreated,
                $this->requireCurrentUser()->getUserIdentifier(),
                Client::class,
                $client->getId(),
                ['name' => $client->getName()],
                $request->getClientIp(),
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
        AuditLogger $auditLogger,
    ): Response {
        if ($client->isArchived()) {
            throw $this->createAccessDeniedException('Ripristinare il cliente prima di modificarlo.');
        }

        $previousName = $client->getName();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clientRepository->save($client, true);
            $auditLogger->log(
                AuditAction::ClientUpdated,
                $this->requireCurrentUser()->getUserIdentifier(),
                Client::class,
                $client->getId(),
                ['previous_name' => $previousName, 'name' => $client->getName()],
                $request->getClientIp(),
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
        AuditLogger $auditLogger,
    ): Response {
        $this->validateCsrf('archive_client_'.$client->getId(), $request);

        if ($projectRepository->countNonArchivedForClient($client) > 0) {
            $this->addFlash('danger', 'Il cliente ha commesse non archiviate e non può essere archiviato.');
        } else {
            $client->archive();
            $clientRepository->save($client, true);
            $auditLogger->log(
                AuditAction::ClientArchived,
                $this->requireCurrentUser()->getUserIdentifier(),
                Client::class,
                $client->getId(),
                ['name' => $client->getName()],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Cliente archiviato.');
        }

        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/{id}/ripristina', name: 'app_client_restore', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function restore(
        Client $client,
        Request $request,
        ClientRepository $clientRepository,
        AuditLogger $auditLogger,
    ): Response {
        $this->validateCsrf('restore_client_'.$client->getId(), $request);
        $client->restore();
        $clientRepository->save($client, true);
        $auditLogger->log(
            AuditAction::ClientRestored,
            $this->requireCurrentUser()->getUserIdentifier(),
            Client::class,
            $client->getId(),
            ['name' => $client->getName()],
            $request->getClientIp(),
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
