<?php

declare(strict_types=1);

namespace App\Service;

final readonly class BackupSummary
{
    public function __construct(
        public string $directory,
        public string $createdAt,
        public string $appVersion,
        public int $attachmentCount,
        public int $attachmentBytes,
        public int $databaseBytes,
        public string $databaseSha256,
    ) {
    }
}
