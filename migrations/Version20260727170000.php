<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727170000 extends AbstractMigration
{
 public function getDescription():string{return 'M3: attività, assegnazioni, avanzamento e stime.';}
 public function up(Schema $s):void{$t=$s->createTable('activity');$t->addColumn('id',Types::INTEGER,['autoincrement'=>true]);$t->addColumn('project_id',Types::INTEGER);$t->addColumn('assignee_id',Types::INTEGER);$t->addColumn('created_by_id',Types::INTEGER);$t->addColumn('title',Types::STRING,['length'=>180]);$t->addColumn('description',Types::TEXT,['notnull'=>false]);$t->addColumn('status',Types::STRING,['length'=>32]);$t->addColumn('priority',Types::STRING,['length'=>24]);$t->addColumn('progress_percent',Types::INTEGER);$t->addColumn('initial_estimated_minutes',Types::INTEGER,['notnull'=>false]);$t->addColumn('remaining_estimated_minutes',Types::INTEGER,['notnull'=>false]);$t->addColumn('start_at',Types::DATETIME_IMMUTABLE,['notnull'=>false]);$t->addColumn('due_at',Types::DATETIME_IMMUTABLE,['notnull'=>false]);$t->addColumn('completed_at',Types::DATETIME_IMMUTABLE,['notnull'=>false]);$t->addColumn('created_at',Types::DATETIME_IMMUTABLE);$t->addColumn('updated_at',Types::DATETIME_IMMUTABLE);$t->setPrimaryKey(['id']);$t->addIndex(['project_id','status'],'idx_activity_project_status');$t->addIndex(['assignee_id','status'],'idx_activity_assignee_status');$t->addIndex(['due_at'],'idx_activity_due_at');$t->addForeignKeyConstraint('project',['project_id'],['id'],['onDelete'=>'CASCADE'],'fk_activity_project');$t->addForeignKeyConstraint('app_user',['assignee_id'],['id'],['onDelete'=>'RESTRICT'],'fk_activity_assignee');$t->addForeignKeyConstraint('app_user',['created_by_id'],['id'],['onDelete'=>'RESTRICT'],'fk_activity_created_by');}
 public function down(Schema $s):void{$s->dropTable('activity');}
}
