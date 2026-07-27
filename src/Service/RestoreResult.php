<?php

declare(strict_types=1);

namespace App\Service;

final readonly class RestoreResult
{
    public function __construct(
        public BackupSummary $restoredBackup,
        public BackupSummary $safetyBackup,
    ) {
    }
}
