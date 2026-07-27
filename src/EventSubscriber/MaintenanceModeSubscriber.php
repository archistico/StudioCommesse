<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\FileLock;
use App\Service\MaintenanceMode;
use App\Service\RequestRuntimeLock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    private const REQUEST_LOCK_ATTRIBUTE = '_studio_commesse_runtime_lock';

    public function __construct(
        private MaintenanceMode $maintenanceMode,
        private RequestRuntimeLock $requestLock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 2048],
            KernelEvents::EXCEPTION => ['onException', -2048],
            KernelEvents::TERMINATE => ['onTerminate', -2048],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $lock = $this->requestLock->acquireShared();
        $request = $event->getRequest();
        $request->attributes->set(self::REQUEST_LOCK_ATTRIBUTE, $lock);

        if (!$this->maintenanceMode->isEnabled()) {
            return;
        }

        $this->release($request);
        $message = htmlspecialchars($this->maintenanceMode->message(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $event->setResponse(new Response(
            '<!doctype html><html lang="it"><head><meta charset="utf-8"><title>Manutenzione</title></head>'
            .'<body><main><h1>Applicazione temporaneamente non disponibile</h1><p>'.$message.'</p></main></body></html>',
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Retry-After' => '60',
                'Cache-Control' => 'no-store, private',
            ],
        ));
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->release($event->getRequest());
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->release($event->getRequest());
        }
    }

    private function release(Request $request): void
    {
        $lock = $request->attributes->get(self::REQUEST_LOCK_ATTRIBUTE);
        if ($lock instanceof FileLock) {
            $lock->release();
        }
        $request->attributes->remove(self::REQUEST_LOCK_ATTRIBUTE);
    }
}
