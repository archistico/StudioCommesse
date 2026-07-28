<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\ActivityStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Query\DashboardSummary;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DashboardRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function summarize(DateTimeImmutable $currentMonth, DateTimeImmutable $nextMonth, ?DateTimeImmutable $now = null): DashboardSummary
    {
        $now ??= new DateTimeImmutable();
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
SELECT
    (SELECT COUNT(project.id)
       FROM project
      WHERE project.archived_at IS NULL
        AND project.status NOT IN (:project_completed, :project_cancelled)) AS open_projects,
    (SELECT COUNT(project.id)
       FROM project
      WHERE project.archived_at IS NULL
        AND project.status = :project_waiting) AS waiting_projects,
    (SELECT COUNT(project.id)
       FROM project
      WHERE project.archived_at IS NULL
        AND project.due_date < :today
        AND project.status NOT IN (:project_completed, :project_cancelled)) AS overdue_projects,
    (SELECT COUNT(client.id)
       FROM client
      WHERE client.archived_at IS NULL) AS active_clients,
    (SELECT COUNT(activity.id)
       FROM activity
      WHERE activity.status NOT IN (:activity_completed, :activity_cancelled)) AS open_activities,
    (SELECT COUNT(activity.id)
       FROM activity
      WHERE activity.due_at < :now
        AND activity.status NOT IN (:activity_completed, :activity_cancelled)) AS overdue_activities,
    (SELECT COALESCE(SUM(MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER))), 0)
       FROM time_entry
      WHERE time_entry.ended_at IS NOT NULL
        AND time_entry.started_at >= :month_from
        AND time_entry.started_at < :month_before) AS worked_minutes,
    (SELECT COUNT(app_user.id)
       FROM app_user
      WHERE app_user.active = 1) AS active_users,
    (SELECT COUNT(app_user.id)
       FROM app_user
      WHERE app_user.active = 1
        AND app_user.role = :partner_role) AS active_partners,
    (SELECT COUNT(app_user.id)
       FROM app_user
      WHERE app_user.active = 1
        AND app_user.role = :collaborator_role) AS active_collaborators
SQL,
            [
                'project_completed' => ProjectStatus::Completed->value,
                'project_cancelled' => ProjectStatus::Cancelled->value,
                'project_waiting' => ProjectStatus::Waiting->value,
                'activity_completed' => ActivityStatus::Completed->value,
                'activity_cancelled' => ActivityStatus::Cancelled->value,
                'today' => $now->format('Y-m-d'),
                'now' => $now->format('Y-m-d H:i:s'),
                'month_from' => $currentMonth->format('Y-m-d H:i:s'),
                'month_before' => $nextMonth->format('Y-m-d H:i:s'),
                'partner_role' => UserRole::Partner->value,
                'collaborator_role' => UserRole::Collaborator->value,
            ],
        );

        if (false === $row) {
            throw new \RuntimeException('Impossibile calcolare il riepilogo della dashboard.');
        }

        return new DashboardSummary(
            openProjects: (int) ($row['open_projects'] ?? 0),
            waitingProjects: (int) ($row['waiting_projects'] ?? 0),
            overdueProjects: (int) ($row['overdue_projects'] ?? 0),
            activeClients: (int) ($row['active_clients'] ?? 0),
            openActivities: (int) ($row['open_activities'] ?? 0),
            overdueActivities: (int) ($row['overdue_activities'] ?? 0),
            workedMinutes: (int) ($row['worked_minutes'] ?? 0),
            activeUsers: (int) ($row['active_users'] ?? 0),
            activePartners: (int) ($row['active_partners'] ?? 0),
            activeCollaborators: (int) ($row['active_collaborators'] ?? 0),
        );
    }
}
