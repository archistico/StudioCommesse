<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final readonly class HttpExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: 'monolog.logger.operations')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 32]];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof HttpExceptionInterface) {
            return;
        }

        $status = $throwable->getStatusCode();
        $template = match ($status) {
            Response::HTTP_FORBIDDEN => 'bundles/TwigBundle/Exception/error403.html.twig',
            Response::HTTP_NOT_FOUND => 'bundles/TwigBundle/Exception/error404.html.twig',
            Response::HTTP_METHOD_NOT_ALLOWED => 'bundles/TwigBundle/Exception/error405.html.twig',
            Response::HTTP_CONFLICT => 'bundles/TwigBundle/Exception/error409.html.twig',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'bundles/TwigBundle/Exception/error422.html.twig',
            Response::HTTP_SERVICE_UNAVAILABLE => 'bundles/TwigBundle/Exception/error503.html.twig',
            default => null,
        };
        if (null === $template) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        $route = $request->attributes->get('_route');
        $this->logger->warning('Richiesta HTTP terminata con errore gestito.', [
            'request_id' => is_string($requestId) ? $requestId : null,
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'route' => is_string($route) ? $route : null,
            'exception_class' => $throwable::class,
        ]);
        $headers = $throwable->getHeaders();
        $headers['Cache-Control'] = 'no-store, private';

        $event->setResponse(new Response(
            $this->twig->render($template, [
                'status_code' => $status,
                'request_id' => is_string($requestId) ? $requestId : null,
            ]),
            $status,
            $headers,
        ));
    }
}
