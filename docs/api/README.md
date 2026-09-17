# ARK med API v1

Base URL: `/api/v1`

- `GET /medicines` supports `q`, `category`, `prescription`, `availability` and pagination.
- `GET /medicines/{slug}` returns one active medicine.
- `POST /orders` validates customer, address and medicine IDs, reloads catalogue data on the server, enforces stock and prescription rules, and creates immutable order snapshots before returning the WhatsApp URL.
- `POST /orders/track` requires the order reference and matching mobile number and returns a privacy-limited status summary.
- `POST /prescription-uploads` accepts up to five JPG, PNG or PDF files, 8 MB each and 20 MB total.
- `POST /enquiries` accepts contact, consultation and health-checkup callback requests with explicit consent.
- `GET /health/ready` verifies the database and private prescription/import storage.

Admin routes require an active Sanctum token and an authorised role. Sensitive responses and all non-GET submissions use `Cache-Control: no-store, private`. Prescription downloads are private and audited.

Validation failures return HTTP 422, authentication failures 401, authorisation failures 403, missing records 404, throttling 429 and unavailable ordering 503. Clients should display the response `message` without exposing raw stack traces.

Medicine records default to inactive. No item from the supplied inventory images should be activated until pharmacy verification is complete.
