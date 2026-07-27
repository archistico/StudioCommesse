<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Activity;
use App\Enum\AttachmentClassification;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentUpload
{
    public ?UploadedFile $file = null;
    public AttachmentClassification $classification = AttachmentClassification::Technical;
    public ?Activity $activity = null;
    public ?string $description = null;
}
