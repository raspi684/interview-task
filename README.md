## Invoice Structure:

The invoice should contain the following fields:
* **Invoice ID**: Auto-generated during creation.
* **Invoice Status**: Possible states include `draft,` `sending,` and `sent-to-client`.
* **Customer Name** 
* **Customer Email** 
* **Invoice Product Lines**, each with:
  * **Product Name**
  * **Quantity**: Integer, must be positive. 
  * **Unit Price**: Integer, must be positive.
  * **Total Unit Price**: Calculated as Quantity x Unit Price. 
* **Total Price**: Sum of all Total Unit Prices.

## Required Endpoints:

1. **View Invoice**: Retrieve invoice data in the format above.
2. **Create Invoice**: Initialize a new invoice.
3. **Send Invoice**: Handle the sending of an invoice.

## Functional Requirements:

### Invoice Criteria:

* An invoice can only be created in `draft` status. 
* An invoice can be created with empty product lines. 
* An invoice can only be sent if it is in `draft` status. 
* An invoice can only be marked as `sent-to-client` if its current status is `sending`. 
* To be sent, an invoice must contain product lines with both quantity and unit price as positive integers greater than **zero**.

### Invoice Sending Workflow:

* **Send an email notification** to the customer using the `NotificationFacade`. 
  * The email's subject and message may be hardcoded or customized as needed. 
  * Change the **Invoice Status** to `sending` when the invoice is sent.

### Delivery:

* Upon successful delivery by the Dummy notification provider:
  * The **Notification Module** triggers a `ResourceDeliveredEvent` via webhook.
  * The **Invoice Module** listens for and captures this event.
  * The **Invoice Status** is updated from `sending` to `sent-to-client`.
  * **Note**: This transition requires that the invoice is currently in the `sending` status.

## Technical Requirements:

* **Preferred Approach**: Domain-Driven Design (DDD) is preferred for this project. If you have experience with DDD, please feel free to apply this methodology. However, if you are more comfortable with another approach, you may choose an alternative structure.
* **Alternative Submission**: If you have a different, comparable project or task that showcases your skills, you may submit that instead of creating this task.
* **Tests**: Core invoice logic must be covered by tests. Choose the testing strategy that best demonstrates the correctness of your solution.
* **Documentation**: Candidates are encouraged to document their decisions and reasoning in comments or a README file, explaining why specific implementations or structures were chosen.

## Evaluation Criteria

We look at the overall shape of the solution, not a checklist. In particular:
* Architecture, separation of concerns, and clarity of module boundaries.
* Testing strategy — what you chose to test and why.
* How the send/deliver workflow behaves when things do not go as expected.

## Note on the Notification Module:

The Notification module included in this repository is a minimal, mock integration example. It is intentionally simple and should not be treated as a reference for DDD structure or for the expected invoice design.

## Setup Instructions:

* Start the project by running `./start.sh`.
* To access the container environment, use: `docker compose exec app bash`.

## Implementation Notes

### Module layout

```
src/Modules/Invoices/
├── Domain/          Invoice aggregate, ProductLine, status rules, repository contract (plain PHP)
├── Application/     Use cases (CreateInvoice, GetInvoice, SendInvoice, MarkInvoiceAsSentToClient),
│                    notifier port, WebhookDeliveredEvent listener
├── Infrastructure/  Query-builder repository, Notifications adapter, service provider
└── Presentation/    Controller, request validation, JSON resource, routes
```

Invoices depends on Notifications only through `Modules\Notifications\Api` (facade, `NotifyData`, `WebhookDeliveredEvent`).

### Endpoints

| Method | Path | Success | Errors |
|---|---|---|---|
| `POST` | `/api/invoices` | `201` draft invoice | `422` validation |
| `GET` | `/api/invoices/{id}` | `200` | `404` |
| `POST` | `/api/invoices/{id}/send` | `202` invoice in `sending` | `404`, `409` not draft, `422` not sendable, `503` notification rejected |

### Running

- Tests: `docker compose exec app php artisan test`
- Delivery webhook for manual testing: `GET /api/notification/hook/delivered/{invoiceId}`

### Decisions

- **Draft vs. send validation.** Quantity and unit price may be `0` in a draft (never negative). Sending requires at least one line and every line to have positive values.
- **Notification is requested inside the send transaction.** The invoice row is locked, moved to `sending`, saved, and `NotificationFacade::notify()` is called before commit. If `notify()` throws, the transaction rolls back, the invoice stays `draft` and the API returns `503` — the request can simply be repeated. A delivery webhook arriving before the commit waits on the row lock and sees `sending`.
- **Retries belong to Notifications.** For invoices a successful `notify()` means "the notification module accepted the request"; the invoice then waits in `sending` for the delivery event.
- **Delivery events are handled idempotently.** A repeated webhook for a `sent-to-client` invoice is a no-op, a webhook for a `draft` invoice is logged as a warning and ignored, and an unknown `resourceId` is ignored because Notifications is generic and the resource may belong to another module. Infrastructure errors still propagate, so the webhook fails with `5xx` and the provider can retry.
- **Event name.** The README mentions `ResourceDeliveredEvent`; the existing `WebhookDeliveredEvent` from the Notifications API is used.
- **Totals are derived**, never stored: `total_unit_price = quantity × unit_price`, `total_price = Σ total_unit_price`. Per-field bounds (quantity ≤ 1,000,000, unit price ≤ 1,000,000,000) and a limit of 100 product lines per invoice keep the arithmetic far from integer overflow.

### Testing strategy

- **Domain unit tests** — every allowed and forbidden status transition, sendability rules, totals.
- **Application unit tests** — `SendInvoice` and `MarkInvoiceAsSentToClient` with an in-memory repository and a spy notifier: notification requested exactly once, never on rule violations, provider failures wrapped; idempotent delivery handling.
- **Feature tests** — HTTP contracts, repository round-trip, provider failure (driver throws → `503`, invoice still `draft`, retry succeeds), and the end-to-end flow create → send → delivery webhook → `sent-to-client`.

### Known limitations

- SQLite ignores `SELECT … FOR UPDATE`; the row lock protects concurrent sends on MySQL/PostgreSQL.
- If `notify()` succeeds but the commit fails, the e-mail is requested while the invoice stays `draft` (a later webhook is ignored). A transactional outbox would close this gap.
- An invoice whose delivery webhook never arrives stays in `sending`; there is no timeout or reconciliation job.
- The webhook is an unauthenticated `GET`; production would need a signed `POST`.
