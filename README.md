# ARK med Progressive Web App

Mobile-first medicine ordering PWA for ARK med Pharmacy & Clinic.

## Development

```bash
npm install
npm run dev
```

## Production build

```bash
npm run build
npm run preview
```

Run the complete local verification suite with `npm run verify`. Before deployment, run `php artisan app:production-check` inside `backend/` and complete [the launch checklist](docs/deployment/LAUNCH_CHECKLIST.md).

The current catalogue cards are deliberate placeholders. Medicine names, strengths, prices, stock and prescription classifications must be imported only after ARK med verifies the `med1.jpg` through `med9.jpg` inventory source images.

Sensitive prescription, customer, order and admin API responses must never be added to service-worker runtime caches.
