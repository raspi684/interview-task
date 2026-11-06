<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Enums;

enum DeliveryConfirmation
{
    case Confirmed;
    case AlreadyConfirmed;
    case NotAwaitingDelivery;
}
