<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApplicationBusyException;

final readonly class AttachmentMutationLock
{
    private FileLockManager $manager;

    public function __construct(string $lockFile)
    {
        $this->manager = new FileLockManager($lockFile);
    }

    public function acquireShared(): FileLock
    {
        $lock = $this->manager->tryAcquireShared();
        if (!$lock instanceof FileLock) {
            throw new ApplicationBusyException('È in corso un backup o un ripristino dello spazio documentale.');
        }

        return $lock;
    }

    public function acquireExclusive(): FileLock
    {
        return $this->manager->acquireExclusive();
    }
}
