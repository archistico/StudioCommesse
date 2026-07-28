<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;

final readonly class AuditPrivacyGuard
{
    public function __construct(private string $secret)
    {
    }

    public function loginIdentifierFingerprint(?string $identifier): ?string
    {
        return $this->fingerprint('login-identifier', $identifier);
    }

    /** @return array<string, bool|float|int|string|null|list<string>> */
    public function logContext(AuditLog $entry): array
    {
        $detailKeys = array_keys($entry->getVisibleDetails());
        sort($detailKeys, SORT_STRING);

        return [
            'actor_fingerprint' => $this->fingerprint('actor', $entry->getActorIdentifier()),
            'subject_type' => $entry->getSubjectType(),
            'subject_id' => $entry->getSubjectId(),
            'detail_keys' => $detailKeys,
            'ip_fingerprint' => $this->fingerprint('ip', $entry->getIpAddress()),
            'request_id' => $entry->getRequestId(),
            'route' => $entry->getRoute(),
            'method' => $entry->getHttpMethod(),
            'occurred_at' => $entry->getOccurredAt()->format(DATE_ATOM),
        ];
    }

    private function fingerprint(string $scope, ?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if ('' === $normalized) {
            return null;
        }

        return substr(hash_hmac('sha256', $scope."\0".$normalized, $this->secret), 0, 24);
    }
}
