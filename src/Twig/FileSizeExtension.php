<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FileSizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('file_size', $this->format(...))];
    }

    public function format(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2, ',', '.').' MiB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KiB';
        }

        return number_format(max(0, $bytes), 0, ',', '.').' B';
    }
}
