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
use App\Security\Voter\ActivityVoter;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use App\Service\WorkDeletionService;
use DomainException;
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
        $this->denyAccessUnlessGranted(ActivityVoter::EDIT, $activity);

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

    #[Route('/{id}/elimina', name: 'app_activity_delete', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PARTNER')]
    public function delete(Activity $activity, Request $request, WorkDeletionService $deletion): Response
    {
        if ($activity->getProject()?->isArchived()) {
            throw $this->createAccessDeniedException('La commessa è archiviata e le attività sono in sola lettura.');
        }
        $activityId = $activity->getId();
        $projectId = $activity->getProject()?->getId();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete_activity_'.$activityId, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            try {
                $deletion->deleteActivity($activity, $this->requireCurrentUser(), $request->getClientIp());
                $this->addFlash('success', 'Attività e registrazioni ore collegate eliminate.');
            } catch (DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }

            return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
        }

        return $this->render('activity/delete.html.twig', [
            'activity' => $activity,
            'impact' => $deletion->activityImpact($activity),
        ]);
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
