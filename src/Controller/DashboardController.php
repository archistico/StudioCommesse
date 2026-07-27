<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Repository\ActivityRepository;
use App\Repository\ClientRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_COLLABORATOR')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        ClientRepository $clientRepository,
        UserRepository $userRepository,
    ): Response {
        $userStatistics = [
            'activeUsers' => $userRepository->countActiveUsers(),
            'activePartners' => $userRepository->countActiveByRole(UserRole::Partner),
            'activeCollaborators' => $userRepository->countActiveByRole(UserRole::Collaborator),
        ];

        return $this->render('dashboard/index.html.twig', [
            'project_statistics' => [
                'open' => $projectRepository->countOpenProjects(),
                'waiting' => $projectRepository->countByStatus(ProjectStatus::Waiting),
                'overdue' => $projectRepository->countOverdue(),
                'clients' => $clientRepository->countActiveClients(),
                'openActivities' => $activityRepository->countOpen(),
                'overdueActivities' => $activityRepository->countOverdue(),
            ],
            'recent_projects' => $projectRepository->findRecentActive(),
            'user_statistics' => $userStatistics,
        ]);
    }
}
