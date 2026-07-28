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
use Twig\Environment;

final readonly class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    private const REQUEST_LOCK_ATTRIBUTE = '_studio_commesse_runtime_lock';

    public function __construct(
        private MaintenanceMode $maintenanceMode,
        private RequestRuntimeLock $requestLock,
        private Environment $twig,
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

        $request = $event->getRequest();
        if ($this->maintenanceMode->isEnabled()) {
            $event->setResponse($this->maintenanceResponse($request));

            return;
        }

        $lock = $this->requestLock->tryAcquireShared();
        if (!$lock instanceof FileLock) {
            $event->setResponse($this->maintenanceResponse($request));

            return;
        }

        $request->attributes->set(self::REQUEST_LOCK_ATTRIBUTE, $lock);

        // Chiude la piccola finestra di gara tra il controllo del marker e il lock condiviso.
        if ($this->maintenanceMode->isEnabled()) {
            $this->release($request);
            $event->setResponse($this->maintenanceResponse($request));
        }
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

    private function maintenanceResponse(Request $request): Response
    {
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);

        return new Response(
            $this->twig->render('bundles/TwigBundle/Exception/error503.html.twig', [
                'status_code' => Response::HTTP_SERVICE_UNAVAILABLE,
                'request_id' => is_string($requestId) ? $requestId : null,
                'maintenance_message' => $this->maintenanceMode->message(),
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Retry-After' => '60',
                'Cache-Control' => 'no-store, private',
            ],
        );
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
