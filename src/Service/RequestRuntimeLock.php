<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class RequestRuntimeLock
{
    public const REQUEST_ATTRIBUTE = '_studio_commesse_runtime_lock';

    private FileLockManager $manager;

    public function __construct(string $lockFile)
    {
        $this->manager = new FileLockManager($lockFile);
    }

    public function acquireShared(): FileLock
    {
        return $this->manager->acquireShared();
    }

    public function tryAcquireShared(): ?FileLock
    {
        return $this->manager->tryAcquireShared();
    }

    public function acquireExclusive(): FileLock
    {
        return $this->manager->acquireExclusive();
    }

    public function tryAcquireExclusiveForRequest(Request $request): ?FileLock
    {
        $sharedLock = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if ($sharedLock instanceof FileLock) {
            $sharedLock->release();
        }
        $request->attributes->remove(self::REQUEST_ATTRIBUTE);

        // Non attendere altre richieste: potrebbero aspettare la sessione di questa richiesta.
        return $this->manager->tryAcquireExclusive();
    }
}
