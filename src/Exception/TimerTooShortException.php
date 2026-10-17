<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\TimeEntry;

final class TimerTooShortException extends \DomainException
{
    public function __construct(public readonly TimeEntry $entry)
    {
        parent::__construct('Il timer deve durare almeno un minuto.');
    }
}
