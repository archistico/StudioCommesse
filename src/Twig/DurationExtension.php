<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DurationExtension extends AbstractExtension
{
    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [new TwigFilter('duration_hm', [$this, 'formatMinutes'])];
    }

    public function formatMinutes(?int $minutes): string
    {
        if (null === $minutes) { return '—'; }
        $minutes = max(0, $minutes);
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
