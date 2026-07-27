<?php

declare(strict_types=1);

namespace App\Service;

final class FileLock
{
    /** @var resource|null */
    private mixed $handle;

    public function __construct(mixed $handle)
    {
        if (!is_resource($handle)) {
            throw new \InvalidArgumentException('Handle di lock non valido.');
        }

        $this->handle = $handle;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}
