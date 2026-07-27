<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allinea il tipo dell’anno del progressivo ed evita il falso autoincremento rilevato da SQLite.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Questa migrazione è prevista per il database SQLite configurato dal progetto.',
        );

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__project_code_sequence '
            .'AS SELECT year_value, last_number FROM project_code_sequence',
        );
        $this->addSql('DROP TABLE project_code_sequence');
        $this->addSql(
            'CREATE TABLE project_code_sequence '
            .'(year_value SMALLINT NOT NULL, last_number INTEGER NOT NULL, PRIMARY KEY (year_value))',
        );
        $this->addSql(
            'INSERT INTO project_code_sequence (year_value, last_number) '
            .'SELECT year_value, last_number FROM __temp__project_code_sequence',
        );
        $this->addSql('DROP TABLE __temp__project_code_sequence');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !($this->connection->getDatabasePlatform() instanceof SQLitePlatform),
            'Questa migrazione è prevista per il database SQLite configurato dal progetto.',
        );

        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__project_code_sequence '
            .'AS SELECT year_value, last_number FROM project_code_sequence',
        );
        $this->addSql('DROP TABLE project_code_sequence');
        $this->addSql(
            'CREATE TABLE project_code_sequence '
            .'(year_value INTEGER NOT NULL, last_number INTEGER NOT NULL, PRIMARY KEY (year_value))',
        );
        $this->addSql(
            'INSERT INTO project_code_sequence (year_value, last_number) '
            .'SELECT year_value, last_number FROM __temp__project_code_sequence',
        );
        $this->addSql('DROP TABLE __temp__project_code_sequence');
    }
}
