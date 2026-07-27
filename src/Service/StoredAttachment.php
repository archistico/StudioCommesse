<?php

declare(strict_types=1);

namespace App\Service;

final readonly class StoredAttachment
{
    public function __construct(
        public string $originalName,
        public string $storageKey,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
    ) {
    }
}
