# ARK med launch checklist

Record an owner, result, timestamp and evidence link for every item. Do not launch with an unresolved critical failure.

## Business approval

- Pharmacy owner approved the business name, address, phone, email, hours and WhatsApp number.
- A pharmacist verified every active medicine, strength, form, pack, price, stock state and prescription classification.
- Privacy, terms, prescription, delivery, refund and cancellation policies are approved and published.
- Service areas, delivery expectations and customer-support responsibilities are documented.

## Infrastructure

- Production domain and HTTPS certificate are active with automatic renewal.
- Production secrets are stored outside source control and access is restricted.
- Database, private prescription storage and public media storage are persistent.
- `php artisan app:production-check` passes.
- Queue worker and scheduler are supervised and restart automatically.
- HTTP-to-HTTPS redirect, SPA fallback, upload limits and cache rules are verified.

## Security and privacy

- `APP_DEBUG=false`; no stack trace or secret appears in an error response.
- `/admin`, `/api`, `.env`, storage, logs, imports and prescriptions are not publicly browsable.
- Each staff role can access only its intended admin areas.
- Inactive staff sessions are rejected and password changes revoke existing tokens.
- Prescription download access creates an audit entry.
- Temporary prescription files are purged on schedule.
- CORS permits only the deployed frontend origin.

## Functional smoke tests

- Install the PWA on Android and iOS-compatible browsers where supported.
- Browse, search, filter, save and add a verified medicine to the cart.
- Complete non-prescription and prescription-required order flows.
- Confirm unavailable stock and expired uploads block checkout.
- Open the signed WhatsApp continuation and track the order with matching details.
- Submit each enquiry type and process it in admin.
- Advance an order and prescription only through allowed transitions.
- Verify CSV exports, media, CMS pages, settings and audit logs.

## Accessibility and compatibility

- Complete keyboard-only navigation including menus, dialogs and forms.
- Verify visible focus, zoom at 200%, reduced motion and increased contrast.
- Check current Chrome, Safari, Firefox and Edge plus representative Android and iPhone sizes.
- Confirm labels, errors and dynamic statuses are announced by a screen reader.

## Operations

- Database and file backups complete, encrypt and copy off-host.
- A restore rehearsal meets the approved recovery targets.
- Uptime, readiness, HTTP 5xx, queue, disk and backup alerts reach the duty operator.
- Log retention, audit retention and prescription retention are approved.
- Rollback artifact, backup identifier and responsible operator are recorded.

## Launch decision

- Final production smoke test is signed by pharmacy operations, pharmacy reviewer and technical owner.
- Monitoring is observed during launch and a rollback decision-maker is available.
