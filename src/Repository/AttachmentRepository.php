<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Project;
use App\Enum\AttachmentClassification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Attachment> */
final class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /** @return list<Attachment> */
    public function findFiltered(
        ?Project $project = null,
        ?Activity $activity = null,
        ?AttachmentClassification $classification = null,
        string $query = '',
    ): array {
        $builder = $this->createQueryBuilder('attachment')
            ->addSelect('project', 'client', 'activity', 'uploadedBy')
            ->join('attachment.project', 'project')
            ->join('project.client', 'client')
            ->leftJoin('attachment.activity', 'activity')
            ->join('attachment.uploadedBy', 'uploadedBy')
            ->orderBy('attachment.createdAt', 'DESC')
            ->addOrderBy('attachment.id', 'DESC');

        if (null !== $project) {
            $builder->andWhere('attachment.project = :project')->setParameter('project', $project);
        }
        if (null !== $activity) {
            $builder->andWhere('attachment.activity = :activity')->setParameter('activity', $activity);
        }
        if (null !== $classification) {
            $builder->andWhere('attachment.classification = :classification')->setParameter('classification', $classification->value);
        }
        if ('' !== trim($query)) {
            $builder
                ->andWhere('LOWER(attachment.originalName) LIKE :query OR LOWER(attachment.description) LIKE :query OR LOWER(project.code) LIKE :query OR LOWER(project.name) LIKE :query OR LOWER(activity.title) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }

        $result = $builder->getQuery()->getResult();

        /** @var list<Attachment> $result */
        return $result;
    }

    public function countForProject(Project $project): int
    {
        return (int) $this->createQueryBuilder('attachment')
            ->select('COUNT(attachment.id)')
            ->andWhere('attachment.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForActivity(Activity $activity): int
    {
        return (int) $this->createQueryBuilder('attachment')
            ->select('COUNT(attachment.id)')
            ->andWhere('attachment.activity = :activity')
            ->setParameter('activity', $activity)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
