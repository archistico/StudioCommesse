<?php

declare(strict_types=1);

namespace App\Service;

use App\Performance\CapacityDatasetSummary;
use App\Performance\CapacityProfile;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Statement;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PerformanceDatasetSeeder
{
    private const PASSWORD = 'Benchmark-accesso-2026!';

    public function __construct(
        private Connection $connection,
        #[Autowire('%app.attachment_storage_dir%')]
        private string $attachmentStorageDirectory,
    ) {
    }

    public function seed(CapacityProfile $profile, bool $reset): CapacityDatasetSummary
    {
        if ($reset) {
            $this->clearBusinessData();
        } elseif ($this->hasBusinessData()) {
            throw new \RuntimeException('Il database benchmark non è vuoto. Usare --reset esclusivamente su un database temporaneo.');
        }

        $this->prepareAttachmentDirectory($reset);
        $now = new DateTimeImmutable('2026-07-28 12:00:00');
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_BCRYPT);

        $this->connection->transactional(function (Connection $connection) use ($profile, $now, $passwordHash): void {
            $this->seedUsers($profile, $now, $passwordHash);
            $this->seedClients($profile, $now);
            $this->seedProjects($profile, $now);
            $activityProjects = $this->seedActivities($profile, $now);
            $this->seedTimeEntries($profile, $activityProjects, $now);
            $this->seedEconomics($profile, $now);
            $this->seedAudit($profile, $now);
            $this->seedAttachments($profile, $now);
            $this->connection->executeStatement(
                'INSERT INTO project_code_sequence (year_value, last_number) VALUES (2026, :last_number)',
                ['last_number' => 1000 + $profile->projectCount()],
            );
        });

        return new CapacityDatasetSummary(
            profile: $profile,
            users: $profile->userCount(),
            clients: $profile->clientCount(),
            projects: $profile->projectCount(),
            activities: $profile->activityCount(),
            timeEntries: $profile->timeEntryCount(),
            expenses: $profile->expenseCount(),
            payments: $profile->paymentCount(),
            audits: $profile->auditCount(),
            attachments: $profile->attachmentCount(),
        );
    }

    private function hasBusinessData(): bool
    {
        foreach (['app_user', 'client', 'project', 'activity', 'time_entry', 'expense', 'payment', 'audit_log', 'attachment'] as $table) {
            if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM '.$table) > 0) {
                return true;
            }
        }

        return false;
    }

    private function clearBusinessData(): void
    {
        $this->connection->transactional(function (Connection $connection): void {
            foreach (['attachment', 'audit_log', 'payment', 'expense', 'time_entry', 'activity', 'project', 'client', 'app_user', 'project_code_sequence'] as $table) {
                $this->connection->executeStatement('DELETE FROM '.$table);
            }
        });
    }

    private function prepareAttachmentDirectory(bool $reset): void
    {
        if ($reset && is_dir($this->attachmentStorageDirectory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->attachmentStorageDirectory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo) {
                    continue;
                }
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
        }
        if (!is_dir($this->attachmentStorageDirectory)
            && !mkdir($this->attachmentStorageDirectory, 0700, true)
            && !is_dir($this->attachmentStorageDirectory)
        ) {
            throw new \RuntimeException('Impossibile creare lo spazio allegati benchmark.');
        }
    }

    private function seedUsers(CapacityProfile $profile, DateTimeImmutable $now, string $passwordHash): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO app_user (id, display_name, username, password, role, active, created_at, updated_at, default_hourly_rate_cents)
VALUES (:id, :display_name, :username, :password, :role, :active, :created_at, :updated_at, :rate)
SQL);
        for ($id = 1; $id <= $profile->userCount(); ++$id) {
            $partner = $id <= 2;
            $this->executePrepared($statement, [
                'id' => $id,
                'display_name' => $partner ? 'Socio Benchmark '.$id : 'Collaboratore Benchmark '.$id,
                'username' => $partner ? 'benchmark.socio'.$id : 'benchmark.collaboratore'.$id,
                'password' => $passwordHash,
                'role' => $partner ? 'ROLE_PARTNER' : 'ROLE_COLLABORATOR',
                'active' => 0 === $id % 13 ? 0 : 1,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
                'rate' => 4_500 + (($id % 8) * 350),
            ]);
        }
    }

    private function seedClients(CapacityProfile $profile, DateTimeImmutable $now): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO client (id, name, contact_person, email, phone, address, tax_code, vat_number, notes, archived_at, created_at, updated_at)
VALUES (:id, :name, :contact, :email, NULL, NULL, NULL, NULL, NULL, :archived_at, :created_at, :updated_at)
SQL);
        for ($id = 1; $id <= $profile->clientCount(); ++$id) {
            $this->executePrepared($statement, [
                'id' => $id,
                'name' => sprintf('Cliente benchmark %03d', $id),
                'contact' => sprintf('Referente %03d', $id),
                'email' => sprintf('cliente%03d@example.test', $id),
                'archived_at' => 0 === $id % 29 ? $now->modify('-10 days')->format('Y-m-d H:i:s') : null,
                'created_at' => $now->modify(sprintf('-%d days', $id % 365))->format('Y-m-d H:i:s'),
                'updated_at' => $now->modify(sprintf('-%d hours', $id % 120))->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedProjects(CapacityProfile $profile, DateTimeImmutable $now): void
    {
        $statuses = ['not_started', 'in_progress', 'waiting', 'completed', 'cancelled'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO project (id, code, name, client_id, responsible_id, status, priority, description, start_date, due_date, waiting_reason, private_note, completed_at, archived_at, created_at, updated_at, estimated_amount_cents, default_hourly_rate_cents)
VALUES (:id, :code, :name, :client_id, :responsible_id, :status, :priority, :description, :start_date, :due_date, :waiting_reason, NULL, :completed_at, :archived_at, :created_at, :updated_at, :estimated, :rate)
SQL);
        for ($id = 1; $id <= $profile->projectCount(); ++$id) {
            $status = $statuses[$id % count($statuses)];
            $created = $now->modify(sprintf('-%d days', $id % 540));
            $due = $now->modify(sprintf('%+d days', ($id % 150) - 45));
            $archived = 0 === $id % 37 ? $now->modify('-5 days')->format('Y-m-d H:i:s') : null;
            $this->executePrepared($statement, [
                'id' => $id,
                'code' => sprintf('2026-%04d', 1000 + $id),
                'name' => sprintf('Commessa benchmark %04d', $id),
                'client_id' => (($id - 1) % $profile->clientCount()) + 1,
                'responsible_id' => (($id - 1) % 2) + 1,
                'status' => $status,
                'priority' => $priorities[$id % count($priorities)],
                'description' => 'Dataset deterministico M9.2-G.',
                'start_date' => in_array($status, ['not_started', 'cancelled'], true) ? null : $created->format('Y-m-d'),
                'due_date' => $due->format('Y-m-d'),
                'waiting_reason' => 'waiting' === $status ? 'Attesa benchmark.' : null,
                'completed_at' => 'completed' === $status ? $now->modify('-2 days')->format('Y-m-d') : null,
                'archived_at' => $archived,
                'created_at' => $created->format('Y-m-d H:i:s'),
                'updated_at' => $now->modify(sprintf('-%d minutes', $id % 3000))->format('Y-m-d H:i:s'),
                'estimated' => 500_000 + (($id % 80) * 25_000),
                'rate' => 5_500 + (($id % 7) * 250),
            ]);
        }
    }

    /** @return array<int, int> activity id => project id */
    private function seedActivities(CapacityProfile $profile, DateTimeImmutable $now): array
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO activity (id, project_id, assignee_id, created_by_id, title, description, status, priority, progress_percent, initial_estimated_minutes, remaining_estimated_minutes, start_at, due_at, completed_at, created_at, updated_at, hourly_rate_override_cents)
VALUES (:id, :project_id, :assignee_id, :created_by_id, :title, :description, :status, :priority, :progress, :initial_minutes, :remaining_minutes, :start_at, :due_at, :completed_at, :created_at, :updated_at, :rate_override)
SQL);
        $statuses = ['not_started', 'in_progress', 'waiting', 'completed', 'cancelled'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $basePerProject = intdiv($profile->activityCount(), $profile->projectCount());
        $extra = $profile->activityCount() % $profile->projectCount();
        $activityId = 0;
        $mapping = [];
        for ($projectId = 1; $projectId <= $profile->projectCount(); ++$projectId) {
            $count = $basePerProject + ($projectId <= $extra ? 1 : 0);
            for ($local = 1; $local <= $count; ++$local) {
                ++$activityId;
                $status = $statuses[$activityId % count($statuses)];
                $progress = 'completed' === $status ? 100 : ('in_progress' === $status ? 55 : 0);
                $initial = 240 + (($activityId % 12) * 60);
                $this->executePrepared($statement, [
                    'id' => $activityId,
                    'project_id' => $projectId,
                    'assignee_id' => (($activityId - 1) % $profile->userCount()) + 1,
                    'created_by_id' => (($projectId - 1) % 2) + 1,
                    'title' => sprintf('Attività benchmark %05d', $activityId),
                    'description' => 'Attività deterministica per il benchmark di capacità.',
                    'status' => $status,
                    'priority' => $priorities[$activityId % count($priorities)],
                    'progress' => $progress,
                    'initial_minutes' => $initial,
                    'remaining_minutes' => 'completed' === $status || 'cancelled' === $status ? 0 : (int) round($initial * (100 - $progress) / 100),
                    'start_at' => $now->modify(sprintf('-%d days', $activityId % 365))->format('Y-m-d H:i:s'),
                    'due_at' => $now->modify(sprintf('%+d days', ($activityId % 120) - 30))->format('Y-m-d H:i:s'),
                    'completed_at' => 'completed' === $status ? $now->modify('-1 day')->format('Y-m-d H:i:s') : null,
                    'created_at' => $now->modify(sprintf('-%d days', $activityId % 500))->format('Y-m-d H:i:s'),
                    'updated_at' => $now->modify(sprintf('-%d minutes', $activityId % 5000))->format('Y-m-d H:i:s'),
                    'rate_override' => 0 === $activityId % 17 ? 7_500 : null,
                ]);
                $mapping[$activityId] = $projectId;
            }
        }

        return $mapping;
    }

    /** @param array<int, int> $activityProjects */
    private function seedTimeEntries(CapacityProfile $profile, array $activityProjects, DateTimeImmutable $now): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO time_entry (id, activity_id, user_id, started_at, ended_at, description, billable, created_at, updated_at, hourly_rate_snapshot_cents, cost_snapshot_cents)
VALUES (:id, :activity_id, :user_id, :started_at, :ended_at, :description, :billable, :created_at, :updated_at, :rate, :cost)
SQL);
        $entryId = 0;
        $periodStart = $now->modify('first day of -11 months midnight');
        foreach ($activityProjects as $activityId => $projectId) {
            for ($local = 1; $local <= 3; ++$local) {
                ++$entryId;
                $started = $periodStart->modify(sprintf('+%d hours', ($entryId * 7) % (24 * 360)));
                $duration = 30 + (($entryId % 8) * 15);
                $running = 0 === $entryId % 997;
                $ended = $running ? null : $started->modify(sprintf('+%d minutes', $duration));
                $rate = 4_800 + (($entryId % 9) * 300);
                $this->executePrepared($statement, [
                    'id' => $entryId,
                    'activity_id' => $activityId,
                    'user_id' => (($activityId + $local - 2) % $profile->userCount()) + 1,
                    'started_at' => $started->format('Y-m-d H:i:s'),
                    'ended_at' => $ended?->format('Y-m-d H:i:s'),
                    'description' => sprintf('Registrazione benchmark %06d · commessa %04d.', $entryId, $projectId),
                    'billable' => 0 === $entryId % 5 ? 0 : 1,
                    'created_at' => $started->format('Y-m-d H:i:s'),
                    'updated_at' => ($ended ?? $started)->format('Y-m-d H:i:s'),
                    'rate' => $rate,
                    'cost' => null === $ended ? 0 : (int) round($duration * $rate / 60),
                ]);
            }
        }
    }

    private function seedEconomics(CapacityProfile $profile, DateTimeImmutable $now): void
    {
        $expense = $this->connection->prepare(<<<'SQL'
INSERT INTO expense (id, project_id, activity_id, recorded_by_id, spent_on, category, description, amount_cents, reimbursable, created_at)
VALUES (:id, :project_id, NULL, :user_id, :spent_on, :category, :description, :amount, :reimbursable, :created_at)
SQL);
        $payment = $this->connection->prepare(<<<'SQL'
INSERT INTO payment (id, project_id, recorded_by_id, paid_on, amount_cents, method, reference, notes, created_at, description)
VALUES (:id, :project_id, :user_id, :paid_on, :amount, :method, :reference, NULL, :created_at, :description)
SQL);
        $expenseId = 0;
        $paymentId = 0;
        for ($projectId = 1; $projectId <= $profile->projectCount(); ++$projectId) {
            for ($local = 1; $local <= 4; ++$local) {
                ++$expenseId;
                $date = $now->modify(sprintf('-%d days', ($expenseId * 3) % 360));
                $this->executePrepared($expense, [
                    'id' => $expenseId,
                    'project_id' => $projectId,
                    'user_id' => (($projectId - 1) % 2) + 1,
                    'spent_on' => $date->format('Y-m-d'),
                    'category' => ['Viaggio', 'Materiali', 'Consulenza', 'Altro'][$local - 1],
                    'description' => sprintf('Spesa benchmark %06d', $expenseId),
                    'amount' => 2_000 + (($expenseId % 40) * 875),
                    'reimbursable' => 0 === $local % 3 ? 1 : 0,
                    'created_at' => $date->format('Y-m-d H:i:s'),
                ]);
            }
            for ($local = 1; $local <= 2; ++$local) {
                ++$paymentId;
                $date = $now->modify(sprintf('-%d days', ($paymentId * 5) % 360));
                $this->executePrepared($payment, [
                    'id' => $paymentId,
                    'project_id' => $projectId,
                    'user_id' => (($projectId - 1) % 2) + 1,
                    'paid_on' => $date->format('Y-m-d'),
                    'amount' => 75_000 + (($paymentId % 60) * 5_000),
                    'method' => 'Bonifico',
                    'reference' => sprintf('BENCH-%06d', $paymentId),
                    'created_at' => $date->format('Y-m-d H:i:s'),
                    'description' => sprintf('Incasso benchmark %06d', $paymentId),
                ]);
            }
        }
    }

    private function seedAudit(CapacityProfile $profile, DateTimeImmutable $now): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO audit_log (id, action, actor_identifier, subject_type, subject_id, details, ip_address, occurred_at)
VALUES (:id, :action, :actor, :subject_type, :subject_id, :details, :ip, :occurred_at)
SQL);
        $actions = ['project.created', 'project.updated', 'activity.created', 'activity.updated', 'time_entry.created', 'expense.created', 'payment.created', 'attachment.uploaded', 'security.login_succeeded', 'security.login_failed', 'security.login_throttled'];
        for ($id = 1; $id <= $profile->auditCount(); ++$id) {
            $projectId = (($id - 1) % $profile->projectCount()) + 1;
            $occurred = $now->modify(sprintf('-%d minutes', $id * 5));
            $this->executePrepared($statement, [
                'id' => $id,
                'action' => $actions[$id % count($actions)],
                'actor' => 0 === $id % 13 ? 'benchmark.socio1' : 'benchmark.collaboratore'.((($id - 1) % max(1, $profile->userCount() - 2)) + 3),
                'subject_type' => 'App\\Entity\\Project',
                'subject_id' => $projectId,
                'details' => json_encode([
                    'project' => sprintf('2026-%04d', 1000 + $projectId),
                    'request_id' => sprintf('BENCH-%08d', $id),
                    'route' => 'app_project_show',
                    'method' => 'GET',
                ], JSON_THROW_ON_ERROR),
                'ip' => '127.0.0.1',
                'occurred_at' => $occurred->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedAttachments(CapacityProfile $profile, DateTimeImmutable $now): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO attachment (id, project_id, activity_id, uploaded_by_id, classification, original_name, storage_key, mime_type, size_bytes, sha256, description, created_at)
VALUES (:id, :project_id, NULL, 1, 'technical', :original_name, :storage_key, 'text/plain', :size_bytes, :sha256, :description, :created_at)
SQL);
        for ($id = 1; $id <= $profile->attachmentCount(); ++$id) {
            $content = sprintf("Documento benchmark %06d\n", $id);
            $storageKey = sprintf(
                '%s/%s.txt',
                $now->format('Y/m'),
                substr(hash('sha256', sprintf('benchmark-attachment-%06d', $id)), 0, 32),
            );
            $path = $this->attachmentStorageDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Impossibile creare una directory allegati benchmark.');
            }
            if (false === file_put_contents($path, $content)) {
                throw new \RuntimeException('Impossibile scrivere un allegato benchmark.');
            }
            $this->executePrepared($statement, [
                'id' => $id,
                'project_id' => (($id - 1) * 5) + 1,
                'original_name' => sprintf('documento-benchmark-%06d.txt', $id),
                'storage_key' => $storageKey,
                'size_bytes' => strlen($content),
                'sha256' => hash('sha256', $content),
                'description' => 'Allegato del dataset di capacità.',
                'created_at' => $now->modify(sprintf('-%d minutes', $id))->format('Y-m-d H:i:s'),
            ]);
        }
    }
    /**
     * @param array<string, int|string|null> $parameters
     */
    private function executePrepared(Statement $statement, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = match (true) {
                null === $value => ParameterType::NULL,
                is_int($value) => ParameterType::INTEGER,
                default => ParameterType::STRING,
            };
            $statement->bindValue($name, $value, $type);
        }

        $statement->executeStatement();
    }

}
