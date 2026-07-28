<?php

declare(strict_types=1);

namespace App\Service;

final readonly class QuarantinedAttachment
{
    public function __construct(
        public string $storageKey,
        public string $originalPath,
        public string $quarantinePath,
    ) {
    }
}
