<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [new TwigFilter('money_eur', [$this, 'formatCents'])];
    }

    public function formatCents(?int $cents): string
    {
        if (null === $cents) {
            return '—';
        }

        return '€ '.number_format($cents / 100, 2, ',', '.');
    }
}
