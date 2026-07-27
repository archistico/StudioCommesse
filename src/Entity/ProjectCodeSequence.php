<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'project_code_sequence')]
class ProjectCodeSequence
{
    #[ORM\Id]
    #[ORM\Column(name: 'year_value', type: Types::SMALLINT)]
    private int $year;

    #[ORM\Column(name: 'last_number')]
    private int $lastNumber;

    public function __construct(int $year, int $lastNumber)
    {
        $this->year = $year;
        $this->lastNumber = $lastNumber;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getLastNumber(): int
    {
        return $this->lastNumber;
    }
}
