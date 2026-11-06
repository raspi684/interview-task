<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailed;
use Modules\Invoices\Domain\Exceptions\InvalidProductLine;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransition;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSent;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFound;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Business rule violations are expected client errors; provider failures (503) stay reported.
        $exceptions->dontReport([
            InvoiceNotFound::class,
            InvalidProductLine::class,
            InvalidStatusTransition::class,
            InvoiceCannotBeSent::class,
        ]);

        $exceptions->render(static fn (InvoiceNotFound $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_NOT_FOUND,
        ));
        $exceptions->render(static fn (InvalidStatusTransition $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_CONFLICT,
        ));
        $exceptions->render(static fn (InvoiceCannotBeSent $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
        $exceptions->render(static fn (InvalidProductLine $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
        $exceptions->render(static fn (InvoiceNotificationFailed $e): JsonResponse => new JsonResponse(
            ['message' => $e->getMessage()],
            Response::HTTP_SERVICE_UNAVAILABLE,
        ));
    })->create();
