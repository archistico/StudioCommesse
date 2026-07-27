<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProjectCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectCodeGenerator $codeGenerator,
    ) {
    }

    public function create(Project $project): void
    {
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($project): void {
            $project->assignCode($this->codeGenerator->nextCode());
            $entityManager->persist($project);
            $entityManager->flush();
        });
    }
}
