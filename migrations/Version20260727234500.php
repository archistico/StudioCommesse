<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M7 allegati protetti per commesse e attività';
    }

    public function up(Schema $schema): void
    {
        $attachment = $schema->createTable('attachment');
        $attachment->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $attachment->addColumn('project_id', Types::INTEGER);
        $attachment->addColumn('activity_id', Types::INTEGER, ['notnull' => false]);
        $attachment->addColumn('uploaded_by_id', Types::INTEGER);
        $attachment->addColumn('classification', Types::STRING, ['length' => 32]);
        $attachment->addColumn('original_name', Types::STRING, ['length' => 255]);
        $attachment->addColumn('storage_key', Types::STRING, ['length' => 255]);
        $attachment->addColumn('mime_type', Types::STRING, ['length' => 127]);
        $attachment->addColumn('size_bytes', Types::INTEGER);
        $attachment->addColumn('sha256', Types::STRING, ['length' => 64]);
        $attachment->addColumn('description', Types::TEXT, ['notnull' => false]);
        $attachment->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $attachment->setPrimaryKey(['id']);
        $attachment->addUniqueIndex(['storage_key'], 'uniq_attachment_storage_key');
        $attachment->addIndex(['project_id', 'created_at'], 'idx_attachment_project_created');
        $attachment->addIndex(['activity_id', 'created_at'], 'idx_attachment_activity_created');
        $attachment->addIndex(['classification'], 'idx_attachment_classification');
        $attachment->addIndex(['uploaded_by_id'], 'idx_attachment_uploaded_by');
        $attachment->addForeignKeyConstraint('project', ['project_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_attachment_project');
        $attachment->addForeignKeyConstraint('activity', ['activity_id'], ['id'], ['onDelete' => 'SET NULL'], 'fk_attachment_activity');
        $attachment->addForeignKeyConstraint('app_user', ['uploaded_by_id'], ['id'], ['onDelete' => 'RESTRICT'], 'fk_attachment_uploaded_by');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('attachment');
    }
}
