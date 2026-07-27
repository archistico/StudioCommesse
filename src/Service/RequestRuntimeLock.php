<?php

declare(strict_types=1);

namespace App\Service;

final readonly class RequestRuntimeLock
{
    private FileLockManager $manager;

    public function __construct(string $lockFile)
    {
        $this->manager = new FileLockManager($lockFile);
    }

    public function acquireShared(): FileLock
    {
        return $this->manager->acquireShared();
    }

    public function acquireExclusive(): FileLock
    {
        return $this->manager->acquireExclusive();
    }
}
