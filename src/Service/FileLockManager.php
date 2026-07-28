<?php

declare(strict_types=1);

namespace App\Service;

final readonly class FileLockManager
{
    public function __construct(private string $lockFile)
    {
    }

    public function acquireShared(): FileLock
    {
        return $this->acquire(LOCK_SH);
    }

    public function acquireExclusive(): FileLock
    {
        return $this->acquire(LOCK_EX);
    }

    public function tryAcquireShared(): ?FileLock
    {
        return $this->tryAcquire(LOCK_SH);
    }

    public function tryAcquireExclusive(): ?FileLock
    {
        return $this->tryAcquire(LOCK_EX);
    }

    /** @param int<0, 7> $operation */
    private function acquire(int $operation): FileLock
    {
        $handle = $this->openHandle();
        if (!flock($handle, $operation)) {
            fclose($handle);
            throw new \RuntimeException('Impossibile acquisire il lock applicativo.');
        }

        return new FileLock($handle);
    }

    /** @param int<0, 7> $operation */
    private function tryAcquire(int $operation): ?FileLock
    {
        $handle = $this->openHandle();
        /** @var int<0, 7> $nonBlockingOperation */
        $nonBlockingOperation = $operation | LOCK_NB;
        if (!flock($handle, $nonBlockingOperation)) {
            fclose($handle);

            return null;
        }

        return new FileLock($handle);
    }

    /** @return resource */
    private function openHandle(): mixed
    {
        $directory = dirname($this->lockFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory dei lock applicativi.');
        }

        $handle = fopen($this->lockFile, 'c+b');
        if (false === $handle) {
            throw new \RuntimeException('Impossibile aprire il lock applicativo.');
        }

        return $handle;
    }
}
