<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class MonthlyReportRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findProjectMetrics(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId = null): array
    {
        $filter = null === $projectId ? '' : 'AND project.id = :project_id';
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_before' => $before->format('Y-m-d H:i:s'),
            'today' => (new DateTimeImmutable('today'))->format('Y-m-d H:i:s'),
        ];
        if (null !== $projectId) {
            $parameters['project_id'] = $projectId;
        }

        return $this->connection->fetchAllAssociative(
            <<<SQL
SELECT project.id AS project_id,
       project.code,
       project.name,
       project.status,
       project.priority,
       project.due_date,
       client.name AS client_name,
       responsible.display_name AS responsible_name,
       COALESCE(activity_metrics.activity_count, 0) AS activity_count,
       COALESCE(activity_metrics.open_activity_count, 0) AS open_activity_count,
       COALESCE(activity_metrics.completed_activity_count, 0) AS completed_activity_count,
       COALESCE(activity_metrics.overdue_activity_count, 0) AS overdue_activity_count,
       COALESCE(activity_metrics.average_progress_percent, 0) AS average_progress_percent,
       COALESCE(activity_metrics.remaining_minutes, 0) AS remaining_minutes,
       COALESCE(work_metrics.time_entry_count, 0) AS time_entry_count,
       COALESCE(work_metrics.worked_minutes, 0) AS worked_minutes,
       COALESCE(work_metrics.billable_minutes, 0) AS billable_minutes,
       COALESCE(work_metrics.contributor_count, 0) AS contributor_count,
       COALESCE(work_metrics.labour_cost_cents, 0) AS labour_cost_cents,
       COALESCE(expense_metrics.expense_count, 0) AS expense_count,
       COALESCE(expense_metrics.expense_cents, 0) AS expense_cents,
       COALESCE(payment_metrics.payment_count, 0) AS payment_count,
       COALESCE(payment_metrics.payment_cents, 0) AS payment_cents,
       COALESCE(attachment_metrics.attachment_count, 0) AS attachment_count,
       MAX(
           COALESCE(work_metrics.last_worked_at, '1970-01-01 00:00:00'),
           COALESCE(expense_metrics.last_expense_at, '1970-01-01 00:00:00'),
           COALESCE(payment_metrics.last_payment_at, '1970-01-01 00:00:00'),
           COALESCE(attachment_metrics.last_attachment_at, '1970-01-01 00:00:00')
       ) AS last_movement_at
FROM project
INNER JOIN client ON client.id = project.client_id
INNER JOIN app_user responsible ON responsible.id = project.responsible_id
LEFT JOIN (
    SELECT activity.project_id,
           COUNT(activity.id) AS activity_count,
           SUM(CASE WHEN activity.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) AS open_activity_count,
           SUM(CASE WHEN activity.status = 'completed' THEN 1 ELSE 0 END) AS completed_activity_count,
           SUM(CASE WHEN activity.due_at IS NOT NULL
                     AND activity.status NOT IN ('completed', 'cancelled')
                     AND activity.due_at < :today THEN 1 ELSE 0 END) AS overdue_activity_count,
           CAST(ROUND(COALESCE(AVG(activity.progress_percent), 0), 0) AS INTEGER) AS average_progress_percent,
           COALESCE(SUM(CASE WHEN activity.status NOT IN ('completed', 'cancelled')
                             THEN COALESCE(activity.remaining_estimated_minutes, 0) ELSE 0 END), 0) AS remaining_minutes
    FROM activity
    GROUP BY activity.project_id
) activity_metrics ON activity_metrics.project_id = project.id
LEFT JOIN (
    SELECT activity.project_id,
           COUNT(time_entry.id) AS time_entry_count,
           COALESCE(SUM(CASE WHEN time_entry.ended_at IS NULL THEN 0
                             ELSE MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) END), 0) AS worked_minutes,
           COALESCE(SUM(CASE WHEN time_entry.billable = 1 AND time_entry.ended_at IS NOT NULL
                             THEN MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) ELSE 0 END), 0) AS billable_minutes,
           COUNT(DISTINCT time_entry.user_id) AS contributor_count,
           COALESCE(SUM(time_entry.cost_snapshot_cents), 0) AS labour_cost_cents,
           MAX(time_entry.started_at) AS last_worked_at
    FROM time_entry
    INNER JOIN activity ON activity.id = time_entry.activity_id
    WHERE time_entry.started_at >= :date_from
      AND time_entry.started_at < :date_before
    GROUP BY activity.project_id
) work_metrics ON work_metrics.project_id = project.id
LEFT JOIN (
    SELECT expense.project_id,
           COUNT(expense.id) AS expense_count,
           COALESCE(SUM(expense.amount_cents), 0) AS expense_cents,
           MAX(expense.spent_on || ' 23:59:59') AS last_expense_at
    FROM expense
    WHERE expense.spent_on >= substr(:date_from, 1, 10)
      AND expense.spent_on < substr(:date_before, 1, 10)
    GROUP BY expense.project_id
) expense_metrics ON expense_metrics.project_id = project.id
LEFT JOIN (
    SELECT payment.project_id,
           COUNT(payment.id) AS payment_count,
           COALESCE(SUM(payment.amount_cents), 0) AS payment_cents,
           MAX(payment.paid_on || ' 23:59:59') AS last_payment_at
    FROM payment
    WHERE payment.paid_on >= substr(:date_from, 1, 10)
      AND payment.paid_on < substr(:date_before, 1, 10)
    GROUP BY payment.project_id
) payment_metrics ON payment_metrics.project_id = project.id
LEFT JOIN (
    SELECT attachment.project_id,
           COUNT(attachment.id) AS attachment_count,
           MAX(attachment.created_at) AS last_attachment_at
    FROM attachment
    WHERE attachment.created_at >= :date_from
      AND attachment.created_at < :date_before
    GROUP BY attachment.project_id
) attachment_metrics ON attachment_metrics.project_id = project.id
WHERE project.archived_at IS NULL
{$filter}
ORDER BY (COALESCE(work_metrics.time_entry_count, 0)
        + COALESCE(expense_metrics.expense_count, 0)
        + COALESCE(payment_metrics.payment_count, 0)
        + COALESCE(attachment_metrics.attachment_count, 0)) DESC,
         project.priority DESC,
         project.code ASC
SQL,
            $parameters,
        );
    }

    /** @return list<array<string, mixed>> */
    public function findTimeEntries(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId = null): array
    {
        $filter = null === $projectId ? '' : 'AND project.id = :project_id';
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_before' => $before->format('Y-m-d H:i:s'),
        ];
        if (null !== $projectId) {
            $parameters['project_id'] = $projectId;
        }

        return $this->connection->fetchAllAssociative(
            <<<SQL
SELECT time_entry.id AS entry_id,
       time_entry.started_at,
       time_entry.ended_at,
       CASE WHEN time_entry.ended_at IS NULL THEN 0
            ELSE MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER)) END AS duration_minutes,
       time_entry.billable,
       time_entry.cost_snapshot_cents,
       COALESCE(time_entry.description, '') AS description,
       worker.id AS user_id,
       worker.display_name AS user_name,
       activity.id AS activity_id,
       activity.title AS activity_title,
       project.id AS project_id,
       project.code AS project_code,
       project.name AS project_name
FROM time_entry
INNER JOIN app_user worker ON worker.id = time_entry.user_id
INNER JOIN activity ON activity.id = time_entry.activity_id
INNER JOIN project ON project.id = activity.project_id
WHERE time_entry.started_at >= :date_from
  AND time_entry.started_at < :date_before
  {$filter}
ORDER BY time_entry.started_at DESC, time_entry.id DESC
SQL,
            $parameters,
        );
    }

    /** @return list<array<string, mixed>> */
    public function findUserCostSummaries(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId = null): array
    {
        $filter = null === $projectId ? '' : 'AND project.id = :project_id';
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_before' => $before->format('Y-m-d H:i:s'),
        ];
        if (null !== $projectId) {
            $parameters['project_id'] = $projectId;
        }

        return $this->connection->fetchAllAssociative(
            <<<SQL
SELECT worker.id AS user_id,
       worker.display_name AS user_name,
       worker.active AS user_active,
       worker.default_hourly_rate_cents AS standard_hourly_rate_cents,
       COUNT(time_entry.id) AS time_entry_count,
       COALESCE(SUM(MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER))), 0) AS worked_minutes,
       COALESCE(SUM(time_entry.cost_snapshot_cents), 0) AS historical_cost_cents
FROM time_entry
INNER JOIN app_user worker ON worker.id = time_entry.user_id
INNER JOIN activity ON activity.id = time_entry.activity_id
INNER JOIN project ON project.id = activity.project_id
WHERE time_entry.started_at >= :date_from
  AND time_entry.started_at < :date_before
  AND time_entry.ended_at IS NOT NULL
  {$filter}
GROUP BY worker.id, worker.display_name, worker.active, worker.default_hourly_rate_cents
ORDER BY worker.display_name COLLATE NOCASE ASC, worker.id ASC
SQL,
            $parameters,
        );
    }

    /** @return list<array<string, mixed>> */
    public function findActionCounts(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId = null): array
    {
        [$filter, $parameters] = $this->auditFilter($from, $before, $projectId);

        return $this->connection->fetchAllAssociative(
            <<<SQL
SELECT audit_log.action, COUNT(audit_log.id) AS action_count
FROM audit_log
WHERE audit_log.occurred_at >= :date_from
  AND audit_log.occurred_at < :date_before
  {$filter}
GROUP BY audit_log.action
ORDER BY action_count DESC, audit_log.action ASC
SQL,
            $parameters,
        );
    }

    /** @return list<array<string, mixed>> */
    public function findActionEvents(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId = null): array
    {
        [$filter, $parameters] = $this->auditFilter($from, $before, $projectId);

        return $this->connection->fetchAllAssociative(
            <<<SQL
SELECT audit_log.id,
       audit_log.occurred_at,
       audit_log.action,
       audit_log.actor_identifier,
       audit_log.subject_type,
       audit_log.subject_id,
       audit_log.details
FROM audit_log
WHERE audit_log.occurred_at >= :date_from
  AND audit_log.occurred_at < :date_before
  {$filter}
ORDER BY audit_log.occurred_at DESC, audit_log.id DESC
SQL,
            $parameters,
        );
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function auditFilter(DateTimeImmutable $from, DateTimeImmutable $before, ?int $projectId): array
    {
        $parameters = [
            'date_from' => $from->format('Y-m-d H:i:s'),
            'date_before' => $before->format('Y-m-d H:i:s'),
        ];
        if (null === $projectId) {
            return ['', $parameters];
        }

        $parameters['project_id'] = $projectId;
        $parameters['project_type'] = Project::class;
        $parameters['activity_type'] = 'App\\Entity\\Activity';
        $parameters['time_entry_type'] = 'App\\Entity\\TimeEntry';
        $parameters['expense_type'] = 'App\\Entity\\Expense';
        $parameters['payment_type'] = 'App\\Entity\\Payment';
        $parameters['attachment_type'] = 'App\\Entity\\Attachment';
        $parameters['project_code'] = (string) $this->connection->fetchOne('SELECT code FROM project WHERE id = :project_id', ['project_id' => $projectId]);

        return [
            <<<'SQL'
AND (
    (audit_log.subject_type = :project_type AND audit_log.subject_id = :project_id)
    OR (audit_log.subject_type = :activity_type AND audit_log.subject_id IN (SELECT id FROM activity WHERE project_id = :project_id))
    OR (audit_log.subject_type = :time_entry_type AND audit_log.subject_id IN (
        SELECT time_entry.id FROM time_entry INNER JOIN activity ON activity.id = time_entry.activity_id WHERE activity.project_id = :project_id
    ))
    OR (audit_log.subject_type = :expense_type AND audit_log.subject_id IN (SELECT id FROM expense WHERE project_id = :project_id))
    OR (audit_log.subject_type = :payment_type AND audit_log.subject_id IN (SELECT id FROM payment WHERE project_id = :project_id))
    OR (audit_log.subject_type = :attachment_type AND audit_log.subject_id IN (SELECT id FROM attachment WHERE project_id = :project_id))
    OR CAST(json_extract(audit_log.details, '$.project_id') AS INTEGER) = :project_id
    OR json_extract(audit_log.details, '$.project') = :project_code
)
SQL,
            $parameters,
        ];
    }
}
