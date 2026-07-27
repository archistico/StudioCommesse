<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727200000 extends AbstractMigration
{
    public function getDescription(): string{return 'M4: registrazioni ore e timer attività.';}
    public function up(Schema $schema): void
    {
        $table=$schema->createTable('time_entry');
        $table->addColumn('id','integer',['autoincrement'=>true]);
        $table->addColumn('activity_id','integer');
        $table->addColumn('user_id','integer');
        $table->addColumn('started_at','datetime_immutable');
        $table->addColumn('ended_at','datetime_immutable',['notnull'=>false]);
        $table->addColumn('description','text',['notnull'=>false]);
        $table->addColumn('billable','boolean',['default'=>true]);
        $table->addColumn('created_at','datetime_immutable');
        $table->addColumn('updated_at','datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['activity_id','started_at'],'idx_time_entry_activity_started');
        $table->addIndex(['user_id','started_at'],'idx_time_entry_user_started');
        $table->addForeignKeyConstraint('activity',['activity_id'],['id'],['onDelete'=>'CASCADE'],'fk_time_entry_activity');
        $table->addForeignKeyConstraint('app_user',['user_id'],['id'],['onDelete'=>'RESTRICT'],'fk_time_entry_user');
    }
    public function down(Schema $schema):void{$schema->dropTable('time_entry');}
}
