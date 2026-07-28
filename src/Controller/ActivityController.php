<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Form\ActivityType;
use App\Repository\ActivityRepository;
use App\Repository\AttachmentRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/attivita')]
#[IsGranted('ROLE_COLLABORATOR')]
final class ActivityController extends AbstractController
{
    #[Route('', name: 'app_activity_index', methods: ['GET'])]
    public function index(
        Request $request,
        ActivityRepository $activityRepository,
        TimeEntryRepository $timeEntryRepository,
        UserRepository $userRepository,
    ): Response {
        $currentUser = $this->requireCurrentUser();
        $assigneeValue = trim((string) $request->query->get('assignee', 'me'));
        $assigneeId = ctype_digit($assigneeValue) && (int) $assigneeValue > 0 ? (int) $assigneeValue : null;
        $selectedAssigneeId = null;
        $targetUser = $currentUser;

        if (null !== $assigneeId) {
            $requestedUser = $userRepository->find($assigneeId);
            if ($requestedUser instanceof User) {
                $targetUser = $requestedUser;
                $selectedAssigneeId = $assigneeId;
            }
        }

        $activities = $activityRepository->findForAssignee($targetUser);
        $activityIds = array_values(array_filter(
            array_map(static fn (Activity $activity): ?int => $activity->getId(), $activities),
            static fn (?int $id): bool => null !== $id,
        ));
        $consumedMinutes = $timeEntryRepository->sumMinutesByActivityIds($activityIds);

        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
            'selected_user' => $targetUser,
            'selected_assignee_id' => $selectedAssigneeId,
            'users' => $userRepository->findAllOrdered(),
            'consumed_minutes' => $consumedMinutes,
        ]);
    }

    #[Route('/commessa/{id}/nuova', name: 'app_activity_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function new(Project $project, Request $request, ActivityRepository $repository, AuditedTransaction $transaction): Response
    {
        if ($project->isArchived()) {
            throw $this->createAccessDeniedException('La commessa è archiviata.');
        }

        $user = $this->requireCurrentUser();
        $activity = (new Activity())
            ->setProject($project)
            ->setCreatedBy($user)
            ->setAssignee($user);
        $form = $this->createForm(ActivityType::class, $activity, ['allow_financial' => $this->isGranted('ROLE_PARTNER')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $transaction->execute(
                static function () use ($repository, $activity): Activity {
                    $repository->save($activity, false);

                    return $activity;
                },
                static fn (Activity $saved): AuditRecord => new AuditRecord(
                    AuditAction::ActivityCreated,
                    $user->getUserIdentifier(),
                    Activity::class,
                    $saved->getId(),
                    ['project' => $project->getCode(), 'title' => $saved->getTitle()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', 'Attività creata.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('activity/form.html.twig', [
            'form' => $form,
            'activity' => $activity,
            'page_title' => 'Nuova attività',
            'submit_label' => 'Crea attività',
            'attachment_count' => 0,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/modifica', name: 'app_activity_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        Activity $activity,
        Request $request,
        ActivityRepository $repository,
        AttachmentRepository $attachmentRepository,
        AuditedTransaction $transaction,
    ): Response
    {
        if ($activity->getProject()?->isArchived()) {
            throw $this->createAccessDeniedException('La commessa è archiviata e le attività sono in sola lettura.');
        }
        if (!$this->canEdit($activity)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ActivityType::class, $activity, ['allow_financial' => $this->isGranted('ROLE_PARTNER')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actorIdentifier = $this->requireCurrentUser()->getUserIdentifier();
            $transaction->execute(
                static function () use ($repository, $activity): Activity {
                    $repository->save($activity, false);

                    return $activity;
                },
                static fn (Activity $saved): AuditRecord => new AuditRecord(
                    AuditAction::ActivityUpdated,
                    $actorIdentifier,
                    Activity::class,
                    $saved->getId(),
                    ['title' => $saved->getTitle()],
                    $request->getClientIp(),
                ),
            );
            $this->addFlash('success', 'Attività aggiornata.');

            return $this->redirectToRoute('app_project_show', ['id' => $activity->getProject()?->getId()]);
        }

        return $this->render('activity/form.html.twig', [
            'form' => $form,
            'activity' => $activity,
            'page_title' => 'Modifica attività',
            'submit_label' => 'Salva',
            'attachment_count' => $attachmentRepository->countForActivity($activity),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function canEdit(Activity $activity): bool
    {
        $user = $this->requireCurrentUser();

        return $this->isGranted('ROLE_PARTNER')
            || $activity->getAssignee()?->getId() === $user->getId()
            || $activity->getCreatedBy()?->getId() === $user->getId()
            || $activity->getProject()?->getResponsible()?->getId() === $user->getId();
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
