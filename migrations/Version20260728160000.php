<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M9.2-G: indici mirati per dashboard, liste recenti, report mensili, controllo e audit.';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('project')->addIndex(['archived_at', 'due_date', 'code'], 'idx_project_active_due_code');
        $schema->getTable('project')->addIndex(['updated_at'], 'idx_project_updated_at');
        $schema->getTable('activity')->addIndex(['assignee_id', 'status', 'due_at'], 'idx_activity_assignee_status_due');
        $schema->getTable('activity')->addIndex(['updated_at'], 'idx_activity_updated_at');
        $schema->getTable('time_entry')->addIndex(['started_at', 'ended_at'], 'idx_time_entry_started_ended');
        $schema->getTable('time_entry')->addIndex(['updated_at'], 'idx_time_entry_updated_at');
        $schema->getTable('expense')->addIndex(['spent_on', 'project_id'], 'idx_expense_date_project');
        $schema->getTable('payment')->addIndex(['paid_on', 'project_id'], 'idx_payment_date_project');
        $schema->getTable('audit_log')->addIndex(['actor_identifier', 'occurred_at'], 'idx_audit_actor_occurred');
        $schema->getTable('audit_log')->addIndex(['subject_type', 'subject_id', 'occurred_at'], 'idx_audit_subject_occurred');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('project')->dropIndex('idx_project_active_due_code');
        $schema->getTable('project')->dropIndex('idx_project_updated_at');
        $schema->getTable('activity')->dropIndex('idx_activity_assignee_status_due');
        $schema->getTable('activity')->dropIndex('idx_activity_updated_at');
        $schema->getTable('time_entry')->dropIndex('idx_time_entry_started_ended');
        $schema->getTable('time_entry')->dropIndex('idx_time_entry_updated_at');
        $schema->getTable('expense')->dropIndex('idx_expense_date_project');
        $schema->getTable('payment')->dropIndex('idx_payment_date_project');
        $schema->getTable('audit_log')->dropIndex('idx_audit_actor_occurred');
        $schema->getTable('audit_log')->dropIndex('idx_audit_subject_occurred');
    }
}
