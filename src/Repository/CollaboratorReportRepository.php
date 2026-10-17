<?php

declare(strict_types=1);

namespace App\Repository;

use App\Query\CollaboratorReportCriteria;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CollaboratorReportRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<array<string, mixed>> */
    public function people(CollaboratorReportCriteria $criteria): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT id, display_name, active FROM app_user WHERE role = 'ROLE_COLLABORATOR' AND (:user_id IS NULL OR id = :user_id) ORDER BY display_name COLLATE NOCASE, id",
            ['user_id' => $criteria->userId],
        );
    }

    /** @return list<array<string, mixed>> */
    public function activities(CollaboratorReportCriteria $criteria): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT a.id, a.assignee_id AS user_id, a.title, a.status, a.progress_percent,
       a.due_at, a.completed_at, a.initial_estimated_minutes, a.remaining_estimated_minutes,
       p.id AS project_id, p.code AS project_code, p.name AS project_name,
       COALESCE((SELECT SUM(MAX(0, CAST((strftime('%s', t.ended_at) - strftime('%s', t.started_at)) / 60 AS INTEGER)))
                 FROM time_entry t WHERE t.activity_id = a.id AND t.ended_at IS NOT NULL), 0) AS actual_minutes
FROM activity a
JOIN project p ON p.id = a.project_id
JOIN app_user u ON u.id = a.assignee_id AND u.role = 'ROLE_COLLABORATOR'
WHERE (:user_id IS NULL OR a.assignee_id = :user_id)
  AND (:project_id IS NULL OR p.id = :project_id)
  AND ((p.archived_at IS NULL AND a.status NOT IN ('completed', 'cancelled'))
       OR (a.status = 'completed' AND a.completed_at >= :date_from AND a.completed_at < :date_before))
ORDER BY a.due_at IS NULL, a.due_at, a.id
SQL,
            $this->parameters($criteria),
        );
    }

    /** @return list<array<string, mixed>> */
    public function entries(CollaboratorReportCriteria $criteria, bool $current = false): array
    {
        // Only the current view ranks history; period totals use the indexed date filter directly.
        $ranking = $current ? 'ROW_NUMBER() OVER (PARTITION BY t.user_id ORDER BY t.started_at DESC, t.id DESC)' : '0';
        $periodFilter = $current ? '' : 'AND t.ended_at IS NOT NULL AND t.started_at >= :date_from AND t.started_at < :date_before';
        $sql = <<<'SQL'
WITH ranked AS (
    SELECT t.id, t.user_id, t.started_at, t.ended_at, t.description, t.billable, t.cost_snapshot_cents,
           a.id AS activity_id, a.title AS activity_title,
           p.id AS project_id, p.code AS project_code, p.name AS project_name, p.archived_at,
           MAX(0, CAST((strftime('%s', t.ended_at) - strftime('%s', t.started_at)) / 60 AS INTEGER)) AS minutes,
           __RANKING__ AS position
    FROM time_entry t
    JOIN activity a ON a.id = t.activity_id
    JOIN project p ON p.id = a.project_id
    JOIN app_user u ON u.id = t.user_id AND u.role = 'ROLE_COLLABORATOR'
    WHERE (:user_id IS NULL OR t.user_id = :user_id)
      AND (:project_id IS NULL OR p.id = :project_id)
      __PERIOD_FILTER__
)
SELECT * FROM ranked
SQL;
        $parameters = $this->parameters($criteria);
        if ($current) {
            unset($parameters['date_from'], $parameters['date_before']);
        }

        $sql = str_replace(['__RANKING__', '__PERIOD_FILTER__'], [$ranking, $periodFilter], $sql);
        if ($current) {
            $sql .= ' WHERE ended_at IS NULL OR position = 1';
        }

        return $this->entityManager->getConnection()->fetchAllAssociative($sql.' ORDER BY started_at DESC, id DESC', $parameters);
    }

    /** @return array{user_id: ?int, project_id: ?int, date_from: string, date_before: string} */
    private function parameters(CollaboratorReportCriteria $criteria): array
    {
        return [
            'user_id' => $criteria->userId, 'project_id' => $criteria->projectId,
            'date_from' => $criteria->from->format('Y-m-d H:i:s'),
            'date_before' => $criteria->before()->format('Y-m-d H:i:s'),
        ];
    }
}
