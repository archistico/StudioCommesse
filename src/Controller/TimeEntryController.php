<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Form\TimeEntryType;
use App\Query\TimeEntrySearchCriteria;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use App\Service\HourlyRateResolver;
use App\Service\TimerService;
use DateTimeImmutable;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ore')]
#[IsGranted('ROLE_COLLABORATOR')]
final class TimeEntryController extends AbstractController
{
    #[Route('', name: 'app_time_entry_index', methods: ['GET'])]
    public function index(
        Request $request,
        TimeEntryRepository $timeEntryRepository,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        UserRepository $userRepository,
    ): Response {
        $selectedProject = $this->findProjectFilter($request, $projectRepository);
        $selectedActivity = $this->findActivityFilter($request, $activityRepository);
        if ($selectedProject instanceof Project
            && $selectedActivity instanceof Activity
            && $selectedActivity->getProject()?->getId() !== $selectedProject->getId()
        ) {
            $selectedActivity = null;
        }
        $selectedUser = $this->findUserFilter($request, $userRepository);

        $fromValue = trim((string) $request->query->get('from', ''));
        $toValue = trim((string) $request->query->get('to', ''));
        $startedFrom = $this->parseDate($fromValue);
        $startedBefore = $this->parseDate($toValue)?->modify('+1 day');
        $dateError = null;
        if (('' !== $fromValue && null === $startedFrom) || ('' !== $toValue && null === $startedBefore)) {
            $dateError = 'Inserire date valide nel formato giorno, mese e anno.';
        } elseif (null !== $startedFrom && null !== $startedBefore && $startedFrom >= $startedBefore) {
            $dateError = 'La data finale non può precedere la data iniziale.';
        }

        $billableValue = (string) $request->query->get('billable', '');
        $billable = match ($billableValue) {
            '1' => true,
            '0' => false,
            default => null,
        };

        $criteria = new TimeEntrySearchCriteria(
            projectId: $selectedProject?->getId(),
            activityId: $selectedActivity?->getId(),
            userId: $selectedUser?->getId(),
            startedFrom: $startedFrom,
            startedBefore: $startedBefore,
            billable: $billable,
            page: max(1, $request->query->getInt('page', 1)),
        );
        $page = $timeEntryRepository->findPage($criteria);
        $paginationPages = range(
            max(1, $page->page - 2),
            min($page->totalPages, $page->page + 2),
        );

        $filterQuery = [];
        if ($selectedProject instanceof Project) {
            $filterQuery['project'] = $selectedProject->getId();
        }
        if ($selectedActivity instanceof Activity) {
            $filterQuery['activity'] = $selectedActivity->getId();
        }
        if ($selectedUser instanceof User) {
            $filterQuery['user'] = $selectedUser->getId();
        }
        if (null !== $startedFrom) {
            $filterQuery['from'] = $startedFrom->format('Y-m-d');
        }
        if (null !== $startedBefore) {
            $filterQuery['to'] = $startedBefore->modify('-1 day')->format('Y-m-d');
        }
        if (null !== $billable) {
            $filterQuery['billable'] = $billable ? '1' : '0';
        }

        return $this->render('time_entry/report.html.twig', [
            'page' => $page,
            'summary' => $timeEntryRepository->summarize($criteria),
            'projects' => $projectRepository->findAllForTimeReporting(),
            'activities' => $activityRepository->findAllForTimeReporting($selectedProject),
            'users' => $userRepository->findAllOrdered(),
            'selected_project' => $selectedProject,
            'selected_activity' => $selectedActivity,
            'selected_user' => $selectedUser,
            'date_from' => $fromValue,
            'date_to' => $toValue,
            'billable_value' => $billableValue,
            'date_error' => $dateError,
            'filter_query' => $filterQuery,
            'pagination_pages' => $paginationPages,
        ]);
    }

    #[Route('/attivita/{id}/nuova', name: 'app_time_entry_new', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function new(
        Activity $activity,
        Request $request,
        TimeEntryRepository $repository,
        HourlyRateResolver $rateResolver,
        AuditLogger $auditLogger,
    ): Response {
        $this->denyUnlessUsable($activity);
        $user = $this->requireCurrentUser();
        $entry = (new TimeEntry())->setActivity($activity)->setUser($user);
        $form = $this->createForm(TimeEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($repository->overlaps($user, $entry->getStartedAt(), $entry->getEndedAt())) {
                $form->addError(new FormError('L’intervallo si sovrappone a un’altra registrazione.'));
            } else {
                $entry->applyRateSnapshot($rateResolver->resolve($activity, $user));
                $repository->save($entry, true);
                $auditLogger->log(
                    AuditAction::TimeEntryCreated,
                    $user->getUserIdentifier(),
                    TimeEntry::class,
                    $entry->getId(),
                    ['activity' => $activity->getTitle()],
                    $request->getClientIp(),
                );
                $this->addFlash('success', 'Ore registrate.');

                return $this->redirectToRoute('app_activity_time', ['id' => $activity->getId()]);
            }
        }

        return $this->render('time_entry/form.html.twig', [
            'form' => $form,
            'activity' => $activity,
            'page_title' => 'Registra ore',
            'submit_label' => 'Salva',
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/attivita/{id}', name: 'app_activity_time', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function activity(Activity $activity, TimeEntryRepository $repository): Response
    {
        return $this->render('time_entry/index.html.twig', [
            'activity' => $activity,
            'entries' => $repository->findForActivity($activity),
            'total_minutes' => $repository->sumMinutesForActivity($activity),
            'running' => $repository->findRunningForUser($this->requireCurrentUser()),
        ]);
    }

    #[Route('/timer/{id}/avvia', name: 'app_timer_start', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function start(Activity $activity, Request $request, TimerService $timer, AuditLogger $auditLogger): Response
    {
        $this->denyUnlessUsable($activity);
        if (!$this->isCsrfTokenValid('timer_start_'.$activity->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->requireCurrentUser();
        try {
            $entry = $timer->start($activity, $user);
            $auditLogger->log(
                AuditAction::TimerStarted,
                $user->getUserIdentifier(),
                TimeEntry::class,
                $entry->getId(),
                ['activity' => $activity->getTitle()],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Timer avviato.');
        } catch (DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_activity_time', ['id' => $activity->getId()]);
    }

    #[Route('/timer/ferma', name: 'app_timer_stop', methods: ['POST'])]
    public function stop(Request $request, TimerService $timer, AuditLogger $auditLogger): Response
    {
        if (!$this->isCsrfTokenValid('timer_stop', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->requireCurrentUser();
        try {
            $entry = $timer->stop($user);
            $auditLogger->log(
                AuditAction::TimerStopped,
                $user->getUserIdentifier(),
                TimeEntry::class,
                $entry->getId(),
                ['minutes' => $entry->getDurationMinutes()],
                $request->getClientIp(),
            );
            $this->addFlash('success', 'Timer fermato.');

            return $this->redirectToRoute('app_activity_time', ['id' => $entry->getActivity()?->getId()]);
        } catch (DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('app_activity_index');
        }
    }

    #[Route('/{id}/modifica', name: 'app_time_entry_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(
        TimeEntry $entry,
        Request $request,
        TimeEntryRepository $repository,
        HourlyRateResolver $rateResolver,
    ): Response
    {
        if (!$this->canManage($entry)) {
            throw $this->createAccessDeniedException();
        }
        if ($entry->isRunning()) {
            throw $this->createAccessDeniedException('Fermare prima il timer.');
        }

        $form = $this->createForm(TimeEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $owner = $entry->getUser();
            if (!$owner instanceof User) {
                throw new \LogicException('Collaboratore mancante.');
            }

            if ($repository->overlaps($owner, $entry->getStartedAt(), $entry->getEndedAt(), $entry->getId())) {
                $form->addError(new FormError('L’intervallo si sovrappone a un’altra registrazione.'));
            } else {
                $activity = $entry->getActivity();
                if (0 === $entry->getHourlyRateSnapshotCents() && $activity instanceof Activity) {
                    $entry->applyRateSnapshot($rateResolver->resolve($activity, $owner));
                } else {
                    $entry->recalculateCostFromSnapshot();
                }
                $repository->save($entry, true);
                $this->addFlash('success', 'Registrazione aggiornata.');

                return $this->redirectToRoute('app_activity_time', ['id' => $entry->getActivity()?->getId()]);
            }
        }

        return $this->render('time_entry/form.html.twig', [
            'form' => $form,
            'activity' => $entry->getActivity(),
            'page_title' => 'Modifica ore',
            'submit_label' => 'Salva',
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function findProjectFilter(Request $request, ProjectRepository $repository): ?Project
    {
        $id = $this->positiveInt($request->query->get('project'));
        $project = null !== $id ? $repository->find($id) : null;

        return $project instanceof Project ? $project : null;
    }

    private function findActivityFilter(Request $request, ActivityRepository $repository): ?Activity
    {
        $id = $this->positiveInt($request->query->get('activity'));
        $activity = null !== $id ? $repository->find($id) : null;

        return $activity instanceof Activity ? $activity : null;
    }

    private function findUserFilter(Request $request, UserRepository $repository): ?User
    {
        $id = $this->positiveInt($request->query->get('user'));
        $user = null !== $id ? $repository->find($id) : null;

        return $user instanceof User ? $user : null;
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ('' === $normalized || !ctype_digit($normalized)) {
            return null;
        }

        $id = (int) $normalized;

        return $id > 0 ? $id : null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    private function denyUnlessUsable(Activity $activity): void
    {
        if ($activity->getProject()?->isArchived()) {
            throw $this->createAccessDeniedException('La commessa è archiviata.');
        }
    }

    private function canManage(TimeEntry $entry): bool
    {
        return $this->isGranted('ROLE_PARTNER') || $entry->getUser()?->getId() === $this->requireCurrentUser()->getId();
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
