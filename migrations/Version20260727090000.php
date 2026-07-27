<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1: utenti, ruoli e registro di audit essenziale.';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('app_user');
        $user->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $user->addColumn('display_name', Types::STRING, ['length' => 120]);
        $user->addColumn('username', Types::STRING, ['length' => 120]);
        $user->addColumn('password', Types::STRING, ['length' => 255]);
        $user->addColumn('role', Types::STRING, ['length' => 32]);
        $user->addColumn('active', Types::BOOLEAN, ['default' => true]);
        $user->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $user->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['username'], 'uniq_app_user_username');
        $user->addIndex(['active', 'role'], 'idx_app_user_active_role');

        $audit = $schema->createTable('audit_log');
        $audit->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $audit->addColumn('action', Types::STRING, ['length' => 64]);
        $audit->addColumn('actor_identifier', Types::STRING, ['length' => 120, 'notnull' => false]);
        $audit->addColumn('subject_type', Types::STRING, ['length' => 120, 'notnull' => false]);
        $audit->addColumn('subject_id', Types::INTEGER, ['notnull' => false]);
        $audit->addColumn('details', Types::JSON);
        $audit->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
        $audit->addColumn('occurred_at', Types::DATETIME_IMMUTABLE);
        $audit->setPrimaryKey(['id']);
        $audit->addIndex(['occurred_at'], 'idx_audit_occurred_at');
        $audit->addIndex(['action'], 'idx_audit_action');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('audit_log');
        $schema->dropTable('app_user');
    }
}
