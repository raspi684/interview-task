<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailed;
use Modules\Invoices\Domain\Exceptions\InvalidCustomer;
use Modules\Invoices\Domain\Exceptions\InvalidProductLine;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Symfony\Component\HttpFoundation\Response;

/** Maps the module's exceptions to HTTP responses, so the module needs no app-level exception config. */
final class InvoiceExceptionRenderer
{
    private const array STATUS_CODES = [
        InvoiceNotFound::class => Response::HTTP_NOT_FOUND,
        InvalidStatusTransition::class => Response::HTTP_CONFLICT,
        InvoiceCannotBeSent::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidProductLine::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidCustomer::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvoiceNotificationFailed::class => Response::HTTP_SERVICE_UNAVAILABLE,
    ];

    public static function register(Handler $handler): void
    {
        // Business rule violations are expected client errors; provider failures (503) stay reported.
        $handler->dontReport([
            InvoiceNotFound::class,
            InvalidStatusTransition::class,
            InvoiceCannotBeSent::class,
            InvalidProductLine::class,
            InvalidCustomer::class,
        ]);

        $handler->renderable(static fn (
            InvoiceNotFound|InvalidStatusTransition|InvoiceCannotBeSent|InvalidProductLine|InvalidCustomer|InvoiceNotificationFailed $e,
        ): JsonResponse => new JsonResponse(['message' => $e->getMessage()], self::STATUS_CODES[$e::class]));
    }
}
