<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Actions\CreateInvoice;
use Modules\Invoices\Application\Actions\GetInvoice;
use Modules\Invoices\Application\Actions\SendInvoice;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceRequest;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class InvoiceController
{
    public function __construct(
        private CreateInvoice $createInvoice,
        private GetInvoice $getInvoice,
        private SendInvoice $sendInvoice,
    ) {}

    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->createInvoice->handle($request->toData());

        return (new InvoiceResource($invoice))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $id): InvoiceResource
    {
        return new InvoiceResource($this->getInvoice->handle(Uuid::fromString($id)));
    }

    public function send(string $id): JsonResponse
    {
        $invoice = $this->sendInvoice->handle(Uuid::fromString($id));

        return (new InvoiceResource($invoice))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
