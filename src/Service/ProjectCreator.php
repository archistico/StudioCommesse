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

    /** Prepara e registra la commessa; flush e audit appartengono alla transazione chiamante. */
    public function create(Project $project): void
    {
        $project->assignCode($this->codeGenerator->nextCode());
        $this->entityManager->persist($project);
    }
}
