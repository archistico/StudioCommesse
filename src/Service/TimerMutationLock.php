<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApplicationBusyException;

final readonly class TimerMutationLock
{
    private FileLockManager $manager;

    public function __construct(string $lockFile)
    {
        $this->manager = new FileLockManager($lockFile);
    }

    public function acquireExclusive(): FileLock
    {
        $lock = $this->manager->tryAcquireExclusive();
        if (!$lock instanceof FileLock) {
            throw new ApplicationBusyException('È già in corso una modifica delle registrazioni ore.');
        }

        return $lock;
    }
}
