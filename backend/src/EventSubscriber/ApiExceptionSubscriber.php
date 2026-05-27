<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Ensures every error under /api is returned as JSON (never an HTML error page) and that
 * internal error details are not leaked to clients in production (SSDLC: don't expose
 * sensitive information in error messages).
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $e = $event->getThrowable();

        $status = match (true) {
            $e instanceof AccessDeniedException => 403,
            $e instanceof AuthenticationException => 401,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };

        $payload = ['error' => $this->messageFor($e, $status)];
        if ($this->debug && 500 === $status) {
            // Only ever in dev: include the real message + class to aid debugging.
            $payload['detail'] = $e->getMessage();
            $payload['exception'] = $e::class;
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }

    private function messageFor(\Throwable $e, int $status): string
    {
        return match ($status) {
            401 => 'Authentication required.',
            403 => 'Access denied.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            429 => 'Too many requests.',
            500 => 'Internal server error.',
            default => $e instanceof HttpExceptionInterface ? ($e->getMessage() ?: 'Request failed.') : 'Request failed.',
        };
    }
}
