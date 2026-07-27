<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M5 finalizzazione economica: descrizione incassi e backfill conservativo dei costi orari';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD COLUMN description VARCHAR(255) DEFAULT NULL');

        $this->addSql(<<<'SQL'
UPDATE time_entry
SET hourly_rate_snapshot_cents = COALESCE(
    NULLIF((SELECT activity.hourly_rate_override_cents FROM activity WHERE activity.id = time_entry.activity_id), 0),
    NULLIF((SELECT project.default_hourly_rate_cents FROM activity INNER JOIN project ON project.id = activity.project_id WHERE activity.id = time_entry.activity_id), 0),
    NULLIF((SELECT app_user.default_hourly_rate_cents FROM app_user WHERE app_user.id = time_entry.user_id), 0),
    0
)
WHERE ended_at IS NOT NULL
  AND hourly_rate_snapshot_cents = 0
SQL);

        $this->addSql(<<<'SQL'
UPDATE time_entry
SET cost_snapshot_cents = CAST(ROUND(
    MAX(0, (CAST(strftime('%s', ended_at) AS INTEGER) - CAST(strftime('%s', started_at) AS INTEGER)) / 60.0)
    * hourly_rate_snapshot_cents / 60.0
) AS INTEGER)
WHERE ended_at IS NOT NULL
  AND cost_snapshot_cents = 0
SQL);
    }

    public function down(Schema $schema): void
    {
        // SQLite non supporta DROP COLUMN in modo uniforme: la descrizione resta innocua in caso di rollback.
        $this->addSql('UPDATE payment SET description = NULL');
    }
}
