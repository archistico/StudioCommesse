<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Form\ProjectType;
use App\Query\ProjectSearchCriteria;
use App\Repository\ActivityRepository;
use App\Repository\AttachmentRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\AuditLogger;
use App\Service\ProjectControlService;
use App\Service\ProjectCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commesse')]
#[IsGranted('ROLE_COLLABORATOR')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
        UserRepository $userRepository,
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $statusValue = (string) $request->query->get('stato', '');
        $priorityValue = (string) $request->query->get('priorita', '');
        $responsibleValue = (string) $request->query->get('responsabile', '');
        $responsibleId = ctype_digit($responsibleValue) ? (int) $responsibleValue : null;

        $criteria = new ProjectSearchCriteria(
            query: $query,
            status: ProjectStatus::tryFrom($statusValue),
            priority: ProjectPriority::tryFrom($priorityValue),
            responsibleId: $responsibleId,
            includeArchived: $request->query->getBoolean('archiviate'),
        );

        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findFiltered($criteria),
            'criteria' => $criteria,
            'statuses' => ProjectStatus::cases(),
            'priorities' => ProjectPriority::cases(),
            'responsibles' => $userRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nuova', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function new(
        Request $request,
        ProjectCreator $projectCreator,
        AuditLogger $auditLogger,
    ): Response {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project, ['allow_administration' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectCreator->create($project);
            $auditLogger->log(
                AuditAction::ProjectCreated,
                $this->requireCurrentUser()->getUserIdentifier(),
                Project::class,
                $project->getId(),
                [
                    'code' => $project->getCode(),
                    'name' => $project->getName(),
                    'client_id' => $project->getClient()?->getId(),
                    'responsible_id' => $project->getResponsible()?->getId(),
                ],
                $request->getClientIp(),
            );
            $this->addFlash('success', sprintf('Commessa %s creata correttamente.', $project->getCode()));

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/form.html.twig', [
            'form' => $form,
            'page_title' => 'Nuova commessa',
            'submit_label' => 'Crea commessa',
            'allow_administration' => true,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_project_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(
        Project $project,
        ActivityRepository $activityRepository,
        TimeEntryRepository $timeEntryRepository,
        AttachmentRepository $attachmentRepository,
        ProjectControlService $projectControlService,
    ): Response {
        $activities = $activityRepository->findForProject($project);
        $activityIds = array_values(array_filter(
            array_map(static fn (Activity $activity): ?int => $activity->getId(), $activities),
            static fn (?int $id): bool => null !== $id,
        ));
        $activityTime = $timeEntryRepository->summarizeMinutesByActivityAndUserIds($activityIds);

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'activities' => $activities,
            'activity_time' => $activityTime,
            'attachment_count' => $attachmentRepository->countForProject($project),
            'closure_control' => $this->isGranted('ROLE_PARTNER') ? $projectControlService->analyze($project) : null,
        ]);
    }

    #[Route('/{id}/modifica', name: 'app_project_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Project $project,
        Request $request,
        ProjectRepository $projectRepository,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);
        $allowAdministration = $this->isGranted('ROLE_PARTNER');
        $previous = [
            'name' => $project->getName(),
            'status' => $project->getStatus()->value,
            'priority' => $project->getPriority()->value,
            'client_id' => $project->getClient()?->getId(),
            'responsible_id' => $project->getResponsible()?->getId(),
        ];

        $form = $this->createForm(ProjectType::class, $project, ['allow_administration' => $allowAdministration]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $projectRepository->save($project, true);
            $auditLogger->log(
                AuditAction::ProjectUpdated,
                $this->requireCurrentUser()->getUserIdentifier(),
                Project::class,
                $project->getId(),
                [
                    'code' => $project->getCode(),
                    'previous_name' => $previous['name'],
                    'name' => $project->getName(),
                    'previous_status' => $previous['status'],
                    'status' => $project->getStatus()->value,
                    'previous_priority' => $previous['priority'],
                    'priority' => $project->getPriority()->value,
                    'previous_client_id' => $previous['client_id'],
                    'client_id' => $project->getClient()?->getId(),
                    'previous_responsible_id' => $previous['responsible_id'],
                    'responsible_id' => $project->getResponsible()?->getId(),
                ],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Commessa aggiornata correttamente.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/form.html.twig', [
            'form' => $form,
            'page_title' => sprintf('Modifica %s', $project->getCode()),
            'submit_label' => 'Salva modifiche',
            'project' => $project,
            'allow_administration' => $allowAdministration,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/archivia', name: 'app_project_archive', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function archive(
        Project $project,
        Request $request,
        ProjectRepository $projectRepository,
        AuditLogger $auditLogger,
    ): Response {
        $this->validateCsrf('archive_project_'.$project->getId(), $request);

        try {
            $project->archive();
            $projectRepository->save($project, true);
            $auditLogger->log(
                AuditAction::ProjectArchived,
                $this->requireCurrentUser()->getUserIdentifier(),
                Project::class,
                $project->getId(),
                ['code' => $project->getCode()],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Commessa archiviata.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/{id}/ripristina', name: 'app_project_restore', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function restore(
        Project $project,
        Request $request,
        ProjectRepository $projectRepository,
        AuditLogger $auditLogger,
    ): Response {
        $this->validateCsrf('restore_project_'.$project->getId(), $request);

        try {
            $project->restore();
            $projectRepository->save($project, true);
            $auditLogger->log(
                AuditAction::ProjectRestored,
                $this->requireCurrentUser()->getUserIdentifier(),
                Project::class,
                $project->getId(),
                ['code' => $project->getCode()],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Commessa ripristinata.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
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
