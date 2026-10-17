<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Project;
use App\Query\ProjectSearchCriteria;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLLABORATOR')]
final class CreationController extends AbstractController
{
    #[Route('/attivita/nuova', name: 'app_activity_choose_project', defaults: ['kind' => 'activity'], methods: ['GET'])]
    #[Route('/documenti/nuovo', name: 'app_attachment_choose_project', defaults: ['kind' => 'attachment'], methods: ['GET'])]
    #[Route('/ore/nuova', name: 'app_time_entry_choose_activity', defaults: ['kind' => 'time'], methods: ['GET'])]
    #[Route('/economia/nuova-spesa', name: 'app_expense_choose_project', defaults: ['kind' => 'expense'], methods: ['GET'])]
    #[Route('/economia/nuovo-incasso', name: 'app_payment_choose_project', defaults: ['kind' => 'payment'], methods: ['GET'])]
    public function choose(string $kind, Request $request, ProjectRepository $projectRepository, ActivityRepository $activityRepository): Response
    {
        if ('payment' === $kind) {
            $this->denyAccessUnlessGranted('ROLE_PARTNER');
        }
        $projects = $projectRepository->findFiltered(new ProjectSearchCriteria());
        $projectId = $request->query->get('project');
        $project = !$request->query->has('project') && 1 === count($projects) ? $projects[0] : null;
        foreach ($projects as $candidate) {
            if ((string) $candidate->getId() === $projectId) {
                $project = $candidate;
                break;
            }
        }

        $activities = null !== $project && 'time' === $kind ? $activityRepository->findForProject($project) : [];
        $activity = null;
        foreach ($activities as $candidate) {
            if ((string) $candidate->getId() === $request->query->get('activity')) {
                $activity = $candidate;
                break;
            }
        }

        $submitted = $request->query->has('continue');
        if ($submitted && $project instanceof Project && ('time' !== $kind || $activity instanceof Activity)) {
            return match ($kind) {
                'activity' => $this->redirectToRoute('app_activity_new', ['id' => $project->getId()]),
                'attachment' => $this->redirectToRoute('app_attachment_new', ['id' => $project->getId()]),
                'expense' => $this->redirectToRoute('app_expense_new', ['id' => $project->getId()]),
                'payment' => $this->redirectToRoute('app_payment_new', ['id' => $project->getId()]),
                default => $this->redirectToRoute('app_time_entry_new', ['id' => $activity?->getId()]),
            };
        }

        return $this->render('creation/choose.html.twig', [
            'kind' => $kind,
            'projects' => $projects,
            'selected_project' => $project,
            'activities' => $activities,
            'selected_activity' => $activity,
            'selection_error' => $submitted,
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
