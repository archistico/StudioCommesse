<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2: clienti, commesse, responsabile unico, stati, priorità, date e progressivo annuale.';
    }

    public function up(Schema $schema): void
    {
        $client = $schema->createTable('client');
        $client->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $client->addColumn('name', Types::STRING, ['length' => 180]);
        $client->addColumn('contact_person', Types::STRING, ['length' => 120, 'notnull' => false]);
        $client->addColumn('email', Types::STRING, ['length' => 180, 'notnull' => false]);
        $client->addColumn('phone', Types::STRING, ['length' => 60, 'notnull' => false]);
        $client->addColumn('address', Types::TEXT, ['notnull' => false]);
        $client->addColumn('tax_code', Types::STRING, ['length' => 32, 'notnull' => false]);
        $client->addColumn('vat_number', Types::STRING, ['length' => 32, 'notnull' => false]);
        $client->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $client->addColumn('archived_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $client->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $client->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $client->setPrimaryKey(['id']);
        $client->addIndex(['archived_at', 'name'], 'idx_client_archived_name');

        $sequence = $schema->createTable('project_code_sequence');
        $sequence->addColumn('year_value', Types::INTEGER);
        $sequence->addColumn('last_number', Types::INTEGER);
        $sequence->setPrimaryKey(['year_value']);

        $project = $schema->createTable('project');
        $project->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $project->addColumn('code', Types::STRING, ['length' => 16]);
        $project->addColumn('name', Types::STRING, ['length' => 180]);
        $project->addColumn('client_id', Types::INTEGER);
        $project->addColumn('responsible_id', Types::INTEGER);
        $project->addColumn('status', Types::STRING, ['length' => 32]);
        $project->addColumn('priority', Types::STRING, ['length' => 24]);
        $project->addColumn('description', Types::TEXT, ['notnull' => false]);
        $project->addColumn('start_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $project->addColumn('due_date', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $project->addColumn('waiting_reason', Types::TEXT, ['notnull' => false]);
        $project->addColumn('private_note', Types::TEXT, ['notnull' => false]);
        $project->addColumn('completed_at', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $project->addColumn('archived_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $project->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $project->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $project->setPrimaryKey(['id']);
        $project->addUniqueIndex(['code'], 'uniq_project_code');
        $project->addIndex(['archived_at', 'status'], 'idx_project_archived_status');
        $project->addIndex(['due_date'], 'idx_project_due_date');
        $project->addIndex(['responsible_id'], 'idx_project_responsible');
        $project->addIndex(['client_id'], 'idx_project_client');
        $project->addForeignKeyConstraint('client', ['client_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_project_client');
        $project->addForeignKeyConstraint('app_user', ['responsible_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_project_responsible');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('project');
        $schema->dropTable('project_code_sequence');
        $schema->dropTable('client');
    }
}
