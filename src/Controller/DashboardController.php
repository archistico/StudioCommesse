<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityRepository;
use App\Repository\DashboardRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLLABORATOR')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(
        DashboardRepository $dashboardRepository,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        TimeEntryRepository $timeEntryRepository,
    ): Response {
        $currentMonth = new DateTimeImmutable('first day of this month midnight');
        $summary = $dashboardRepository->summarize($currentMonth, $currentMonth->modify('+1 month'));

        return $this->render('dashboard/index.html.twig', [
            'project_statistics' => [
                'open' => $summary->openProjects,
                'waiting' => $summary->waitingProjects,
                'overdue' => $summary->overdueProjects,
                'clients' => $summary->activeClients,
                'openActivities' => $summary->openActivities,
                'overdueActivities' => $summary->overdueActivities,
                'workedMinutes' => $summary->workedMinutes,
            ],
            'recent_projects' => $projectRepository->findRecentActive(),
            'recent_activities' => $activityRepository->findRecentlyUpdated(),
            'recent_time_entries' => $timeEntryRepository->findRecentlyUpdated(),
            'user_statistics' => [
                'activeUsers' => $summary->activeUsers,
                'activePartners' => $summary->activePartners,
                'activeCollaborators' => $summary->activeCollaborators,
            ],
        ]);
    }
}
