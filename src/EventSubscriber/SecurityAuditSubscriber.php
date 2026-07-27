<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\AuditAction;
use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private AuditLogger $auditLogger)
    {
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
            [],
            $event->getRequest()->getClientIp(),
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $request->request->getString('_username');

        $this->auditLogger->log(
            AuditAction::LoginFailed,
            '' !== $identifier ? mb_strtolower(trim($identifier)) : null,
            null,
            null,
            ['reason' => $event->getException()->getMessageKey()],
            $request->getClientIp(),
        );
    }
}
