<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApplicationBusyException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final readonly class DatabaseExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: 'monolog.logger.operations')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getThrowable() instanceof HttpExceptionInterface) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ApplicationBusyException || $this->isDatabaseBusy($throwable)) {
            $status = Response::HTTP_SERVICE_UNAVAILABLE;
            $template = 'bundles/TwigBundle/Exception/error503.html.twig';
            $retryAfter = '2';
        } elseif ($this->contains($throwable, UniqueConstraintViolationException::class)) {
            $status = Response::HTTP_CONFLICT;
            $template = 'bundles/TwigBundle/Exception/error409.html.twig';
            $retryAfter = null;
        } else {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        $route = $request->attributes->get('_route');
        $this->logger->error('Operazione applicativa non completata.', [
            'request_id' => is_string($requestId) ? $requestId : null,
            'exception_class' => $throwable::class,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'route' => is_string($route) ? $route : null,
            'status' => $status,
        ]);

        $headers = ['Cache-Control' => 'no-store, private'];
        if (null !== $retryAfter) {
            $headers['Retry-After'] = $retryAfter;
        }

        $event->setResponse(new Response(
            $this->twig->render($template, [
                'status_code' => $status,
                'request_id' => is_string($requestId) ? $requestId : null,
            ]),
            $status,
            $headers,
        ));
    }

    private function isDatabaseBusy(\Throwable $throwable): bool
    {
        if ($this->contains($throwable, LockWaitTimeoutException::class)) {
            return true;
        }

        do {
            $message = strtolower($throwable->getMessage());
            if (str_contains($message, 'database is locked')
                || str_contains($message, 'database table is locked')
                || str_contains($message, 'database is busy')
            ) {
                return true;
            }
            $throwable = $throwable->getPrevious();
        } while ($throwable instanceof \Throwable);

        return false;
    }

    /** @param class-string<\Throwable> $class */
    private function contains(\Throwable $throwable, string $class): bool
    {
        do {
            if ($throwable instanceof $class) {
                return true;
            }
            $throwable = $throwable->getPrevious();
        } while ($throwable instanceof \Throwable);

        return false;
    }
}
