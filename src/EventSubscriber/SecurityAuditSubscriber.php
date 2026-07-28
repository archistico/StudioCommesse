<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Service\AuditLogger;
use App\Service\AuditPrivacyGuard;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AuditPrivacyGuard $privacyGuard,
        private int $loginLockoutMinutes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $identifier = $user instanceof User ? $user->getUserIdentifier() : null;

        $this->auditLogger->log(
            AuditAction::LoginSucceeded,
            $identifier,
            User::class,
            $user instanceof User ? $user->getId() : null,
            ['identifier_fingerprint' => $this->privacyGuard->loginIdentifierFingerprint($identifier)],
            $event->getRequest()->getClientIp(),
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = mb_strtolower(trim($request->request->getString('_username')));
        $throttled = $event->getException() instanceof TooManyLoginAttemptsAuthenticationException;
        $details = [
            'failure_category' => $throttled ? 'temporarily_throttled' : 'credentials_rejected',
            'identifier_fingerprint' => $this->privacyGuard->loginIdentifierFingerprint($identifier),
        ];
        if ($throttled) {
            $details['lockout_minutes'] = $this->loginLockoutMinutes;
        }

        $this->auditLogger->log(
            $throttled ? AuditAction::LoginThrottled : AuditAction::LoginFailed,
            null,
            null,
            null,
            $details,
            $request->getClientIp(),
        );
    }
}
