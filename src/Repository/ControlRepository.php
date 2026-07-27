<?php

declare(strict_types=1);

namespace App\Repository;

use App\Query\CollaboratorEvaluationCriteria;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ControlRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findProjectMetrics(?int $clientId = null, ?int $responsibleId = null, ?int $projectId = null): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
WITH activity_metrics AS (
    SELECT activity.project_id,
           COUNT(activity.id) AS activity_count,
           COALESCE(SUM(CASE WHEN activity.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END), 0) AS open_activity_count,
           COALESCE(SUM(COALESCE(activity.initial_estimated_minutes, 0)), 0) AS estimated_minutes,
           COALESCE(SUM(CASE WHEN activity.status NOT IN ('completed', 'cancelled') THEN COALESCE(activity.remaining_estimated_minutes, 0) ELSE 0 END), 0) AS remaining_minutes,
           MAX(activity.updated_at) AS last_activity_at
    FROM activity
    GROUP BY activity.project_id
),
time_metrics AS (
    SELECT activity.project_id,
           COALESCE(SUM(CASE WHEN time_entry.ended_at IS NULL THEN 0 ELSE MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) END), 0) AS actual_minutes,
           COALESCE(SUM(CASE WHEN time_entry.ended_at IS NULL THEN 0 ELSE time_entry.cost_snapshot_cents END), 0) AS labour_cost_cents,
           COALESCE(SUM(CASE WHEN time_entry.ended_at IS NULL THEN 1 ELSE 0 END), 0) AS running_timer_count,
           MAX(COALESCE(time_entry.ended_at, time_entry.started_at)) AS last_time_at
    FROM time_entry
    INNER JOIN activity ON activity.id = time_entry.activity_id
    GROUP BY activity.project_id
),
expense_metrics AS (
    SELECT expense.project_id, COALESCE(SUM(expense.amount_cents), 0) AS expense_cents
    FROM expense
    GROUP BY expense.project_id
),
payment_metrics AS (
    SELECT payment.project_id, COALESCE(SUM(payment.amount_cents), 0) AS payment_cents
    FROM payment
    GROUP BY payment.project_id
)
SELECT project.id AS project_id,
       project.code,
       project.name,
       project.status,
       project.due_date,
       project.estimated_amount_cents,
       client.id AS client_id,
       client.name AS client_name,
       responsible.id AS responsible_id,
       responsible.display_name AS responsible_name,
       COALESCE(activity_metrics.activity_count, 0) AS activity_count,
       COALESCE(activity_metrics.open_activity_count, 0) AS open_activity_count,
       COALESCE(activity_metrics.estimated_minutes, 0) AS estimated_minutes,
       COALESCE(activity_metrics.remaining_minutes, 0) AS remaining_minutes,
       COALESCE(time_metrics.actual_minutes, 0) AS actual_minutes,
       COALESCE(time_metrics.labour_cost_cents, 0) AS labour_cost_cents,
       COALESCE(time_metrics.running_timer_count, 0) AS running_timer_count,
       COALESCE(expense_metrics.expense_cents, 0) AS expense_cents,
       COALESCE(payment_metrics.payment_cents, 0) AS payment_cents,
       MAX(
           COALESCE(project.updated_at, '1970-01-01 00:00:00'),
           COALESCE(activity_metrics.last_activity_at, '1970-01-01 00:00:00'),
           COALESCE(time_metrics.last_time_at, '1970-01-01 00:00:00')
       ) AS last_operational_at
FROM project
INNER JOIN client ON client.id = project.client_id
INNER JOIN app_user AS responsible ON responsible.id = project.responsible_id
LEFT JOIN activity_metrics ON activity_metrics.project_id = project.id
LEFT JOIN time_metrics ON time_metrics.project_id = project.id
LEFT JOIN expense_metrics ON expense_metrics.project_id = project.id
LEFT JOIN payment_metrics ON payment_metrics.project_id = project.id
WHERE project.archived_at IS NULL
  AND (:client_id IS NULL OR project.client_id = :client_id)
  AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
  AND (:project_id IS NULL OR project.id = :project_id)
ORDER BY project.code DESC
SQL,
            [
                'client_id' => $clientId,
                'responsible_id' => $responsibleId,
                'project_id' => $projectId,
            ],
        );

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function findCollaboratorMetrics(
        DateTimeImmutable $from,
        DateTimeImmutable $before,
        ?int $clientId = null,
        ?int $responsibleId = null,
        ?DateTimeImmutable $now = null,
    ): array {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
WITH workload AS (
    SELECT activity.assignee_id AS user_id,
           COUNT(activity.id) AS open_activities,
           COALESCE(SUM(CASE WHEN activity.due_at < :now THEN 1 ELSE 0 END), 0) AS overdue_activities,
           COALESCE(SUM(COALESCE(activity.remaining_estimated_minutes, 0)), 0) AS remaining_minutes
    FROM activity
    INNER JOIN project ON project.id = activity.project_id
    WHERE project.archived_at IS NULL
      AND activity.status NOT IN ('completed', 'cancelled')
      AND (:client_id IS NULL OR project.client_id = :client_id)
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
    GROUP BY activity.assignee_id
),
worked AS (
    SELECT time_entry.user_id,
           COALESCE(SUM(MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER))), 0) AS worked_minutes,
           COALESCE(SUM(CASE WHEN time_entry.billable = 1 THEN MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) ELSE 0 END), 0) AS billable_minutes,
           COUNT(DISTINCT activity.project_id) AS project_count
    FROM time_entry
    INNER JOIN activity ON activity.id = time_entry.activity_id
    INNER JOIN project ON project.id = activity.project_id
    WHERE project.archived_at IS NULL
      AND time_entry.ended_at IS NOT NULL
      AND time_entry.started_at >= :date_from
      AND time_entry.started_at < :date_before
      AND (:client_id IS NULL OR project.client_id = :client_id)
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
    GROUP BY time_entry.user_id
)
SELECT app_user.id AS user_id,
       app_user.display_name,
       app_user.role,
       app_user.active,
       COALESCE(workload.open_activities, 0) AS open_activities,
       COALESCE(workload.overdue_activities, 0) AS overdue_activities,
       COALESCE(workload.remaining_minutes, 0) AS remaining_minutes,
       COALESCE(worked.worked_minutes, 0) AS worked_minutes,
       COALESCE(worked.billable_minutes, 0) AS billable_minutes,
       COALESCE(worked.project_count, 0) AS project_count
FROM app_user
LEFT JOIN workload ON workload.user_id = app_user.id
LEFT JOIN worked ON worked.user_id = app_user.id
WHERE app_user.active = 1 OR workload.open_activities IS NOT NULL OR worked.worked_minutes IS NOT NULL
ORDER BY app_user.active DESC, app_user.display_name ASC
SQL,
            [
                'now' => ($now ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_before' => $before->format('Y-m-d H:i:s'),
                'client_id' => $clientId,
                'responsible_id' => $responsibleId,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function findCollaboratorWorkEntries(CollaboratorEvaluationCriteria $criteria): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT time_entry.id AS time_entry_id,
       project.id AS project_id,
       project.code AS project_code,
       project.name AS project_name,
       activity.id AS activity_id,
       activity.title AS activity_title,
       time_entry.started_at,
       time_entry.ended_at,
       time_entry.billable,
       time_entry.description,
       MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) AS duration_minutes
FROM time_entry
INNER JOIN activity ON activity.id = time_entry.activity_id
INNER JOIN project ON project.id = activity.project_id
WHERE project.archived_at IS NULL
  AND time_entry.user_id = :user_id
  AND time_entry.ended_at IS NOT NULL
  AND time_entry.started_at >= :date_from
  AND time_entry.started_at < :date_before
  AND (:client_id IS NULL OR project.client_id = :client_id)
  AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
  AND (:project_id IS NULL OR project.id = :project_id)
  AND (:billable IS NULL OR time_entry.billable = :billable)
ORDER BY date(time_entry.started_at) DESC, time_entry.started_at ASC, time_entry.id ASC
SQL,
            [
                'user_id' => $criteria->userId,
                'date_from' => $criteria->periodFrom->format('Y-m-d H:i:s'),
                'date_before' => $criteria->periodBefore->format('Y-m-d H:i:s'),
                'client_id' => $criteria->clientId,
                'responsible_id' => $criteria->responsibleId,
                'project_id' => $criteria->projectId,
                'billable' => null === $criteria->billable ? null : ($criteria->billable ? 1 : 0),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function findClientPeriodMetrics(
        DateTimeImmutable $from,
        DateTimeImmutable $before,
        ?int $clientId = null,
        ?int $responsibleId = null,
    ): array {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT client.id AS client_id,
       client.name AS client_name,
       COALESCE(SUM(metrics.worked_minutes), 0) AS worked_minutes,
       COALESCE(SUM(metrics.billable_minutes), 0) AS billable_minutes,
       COALESCE(SUM(metrics.labour_cost_cents), 0) AS labour_cost_cents,
       COALESCE(SUM(metrics.expense_cents), 0) AS expense_cents,
       COALESCE(SUM(metrics.payment_cents), 0) AS payment_cents
FROM client
LEFT JOIN (
    SELECT project.client_id,
           MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) AS worked_minutes,
           CASE WHEN time_entry.billable = 1 THEN MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) ELSE 0 END AS billable_minutes,
           time_entry.cost_snapshot_cents AS labour_cost_cents,
           0 AS expense_cents,
           0 AS payment_cents
    FROM time_entry
    INNER JOIN activity ON activity.id = time_entry.activity_id
    INNER JOIN project ON project.id = activity.project_id
    WHERE project.archived_at IS NULL
      AND time_entry.ended_at IS NOT NULL
      AND time_entry.started_at >= :date_from
      AND time_entry.started_at < :date_before
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)

    UNION ALL

    SELECT project.client_id, 0, 0, 0, expense.amount_cents, 0
    FROM expense
    INNER JOIN project ON project.id = expense.project_id
    WHERE project.archived_at IS NULL
      AND expense.spent_on >= :date_from_date
      AND expense.spent_on < :date_before_date
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)

    UNION ALL

    SELECT project.client_id, 0, 0, 0, 0, payment.amount_cents
    FROM payment
    INNER JOIN project ON project.id = payment.project_id
    WHERE project.archived_at IS NULL
      AND payment.paid_on >= :date_from_date
      AND payment.paid_on < :date_before_date
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
) AS metrics ON metrics.client_id = client.id
WHERE (:client_id IS NULL OR client.id = :client_id)
GROUP BY client.id, client.name
HAVING worked_minutes > 0 OR expense_cents > 0 OR payment_cents > 0
ORDER BY client.name ASC
SQL,
            [
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_before' => $before->format('Y-m-d H:i:s'),
                'date_from_date' => $from->format('Y-m-d'),
                'date_before_date' => $before->format('Y-m-d'),
                'client_id' => $clientId,
                'responsible_id' => $responsibleId,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function findPeriodMetrics(
        DateTimeImmutable $from,
        DateTimeImmutable $before,
        ?int $clientId = null,
        ?int $responsibleId = null,
    ): array {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT metrics.month_key,
       COALESCE(SUM(metrics.worked_minutes), 0) AS worked_minutes,
       COALESCE(SUM(metrics.billable_minutes), 0) AS billable_minutes,
       COALESCE(SUM(metrics.labour_cost_cents), 0) AS labour_cost_cents,
       COALESCE(SUM(metrics.expense_cents), 0) AS expense_cents,
       COALESCE(SUM(metrics.payment_cents), 0) AS payment_cents
FROM (
    SELECT strftime('%Y-%m', time_entry.started_at) AS month_key,
           MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) AS worked_minutes,
           CASE WHEN time_entry.billable = 1 THEN MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) ELSE 0 END AS billable_minutes,
           time_entry.cost_snapshot_cents AS labour_cost_cents,
           0 AS expense_cents,
           0 AS payment_cents
    FROM time_entry
    INNER JOIN activity ON activity.id = time_entry.activity_id
    INNER JOIN project ON project.id = activity.project_id
    WHERE project.archived_at IS NULL
      AND time_entry.ended_at IS NOT NULL
      AND time_entry.started_at >= :date_from
      AND time_entry.started_at < :date_before
      AND (:client_id IS NULL OR project.client_id = :client_id)
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)

    UNION ALL

    SELECT strftime('%Y-%m', expense.spent_on), 0, 0, 0, expense.amount_cents, 0
    FROM expense
    INNER JOIN project ON project.id = expense.project_id
    WHERE project.archived_at IS NULL
      AND expense.spent_on >= :date_from_date
      AND expense.spent_on < :date_before_date
      AND (:client_id IS NULL OR project.client_id = :client_id)
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)

    UNION ALL

    SELECT strftime('%Y-%m', payment.paid_on), 0, 0, 0, 0, payment.amount_cents
    FROM payment
    INNER JOIN project ON project.id = payment.project_id
    WHERE project.archived_at IS NULL
      AND payment.paid_on >= :date_from_date
      AND payment.paid_on < :date_before_date
      AND (:client_id IS NULL OR project.client_id = :client_id)
      AND (:responsible_id IS NULL OR project.responsible_id = :responsible_id)
) AS metrics
GROUP BY metrics.month_key
ORDER BY metrics.month_key ASC
SQL,
            [
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_before' => $before->format('Y-m-d H:i:s'),
                'date_from_date' => $from->format('Y-m-d'),
                'date_before_date' => $before->format('Y-m-d'),
                'client_id' => $clientId,
                'responsible_id' => $responsibleId,
            ],
        );
    }
}
