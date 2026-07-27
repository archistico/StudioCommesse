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

    /** @param int<0, 7> $operation */
    private function acquire(int $operation): FileLock
    {
        $directory = dirname($this->lockFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory dei lock applicativi.');
        }

        $handle = fopen($this->lockFile, 'c+b');
        if (false === $handle) {
            throw new \RuntimeException('Impossibile aprire il lock applicativo.');
        }
        if (!flock($handle, $operation)) {
            fclose($handle);
            throw new \RuntimeException('Impossibile acquisire il lock applicativo.');
        }

        return new FileLock($handle);
    }
}
