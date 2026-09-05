# Shamba Track

An offline-first Progressive Web App that helps poultry farmers in Tanzania (and beyond) track the real cost, production, and profit of raising layers, broilers, improved kienyeji, and sasso chickens — including when there's no internet connection.

## Why

Farmers currently track costs (feed, coops, land, labor, medication, vaccination, transport) and production (eggs, sales, mortality) informally, if at all. Shamba Track turns that into structured records and calculates the numbers a farmer actually needs: real cost per bird per day, feed conversion ratio, break-even point, and true profit — gross, net of loan interest, per bird, per batch.

## Core principles

- **Offline-first.** Every feature must work with no internet connection. The app only needs connectivity to sync.
- **Low-typing, high-tap.** Designed for users who may have low literacy or limited typing comfort — large tap targets, icons, minimal free text.
- **Free, deterministic smart features in v1.** FCR calculation, breed-benchmark comparisons, anomaly flags, break-even calculators, and vaccination scheduling all run as plain logic — no AI API costs, no dependency on being online to be useful.
- **Multi-tenant-ready from day one.** Every table carries a `farm_id`, even though v1 ships as a single-farmer experience. This avoids a painful migration when cooperative/extension-officer support is added later.

## Tech stack

- **Backend:** PHP + PDO (MySQL), no framework — matches the deployment environment
- **Frontend:** PWA — HTML/CSS/vanilla JS, service worker + IndexedDB for offline storage (IndexedDB layer arrives Phase 3)
- **Auth:** Phone number + OTP, persistent device session (no repeated login)
- **Deployment:** Single subdomain root — **no `public/` or `public_html/` wrapper folder.** All app files sit directly at the web root; internal folders (`config/`, `src/`, `sql/`, `includes/`) are blocked from direct web access via `.htaccess`.

## Project structure

```
/                       ← subdomain document root
├── index.php           ← app entry point
├── manifest.webmanifest
├── service-worker.js
├── offline.html
├── .htaccess           ← blocks direct access to internal folders
├── config/
│   ├── config.example.php   ← copy to config.php and fill in real values
│   └── config.php           ← git-ignored, real secrets
├── includes/
│   └── bootstrap.php
├── src/
│   ├── Core/            ← Database, Autoloader
│   ├── Models/           ← (Phase 2+)
│   └── Controllers/      ← (Phase 2+)
├── api/                  ← JSON API endpoints (Phase 1+)
├── assets/
│   ├── css/app.css
│   ├── js/app.js
│   └── icons/
└── sql/
    ├── 001_base_schema.sql
    └── 002_seed_currencies.sql
```

## Setup

1. Create a MySQL database.
2. Import the schema in order:
   ```
   mysql -u youruser -p yourdb < sql/001_base_schema.sql
   mysql -u youruser -p yourdb < sql/002_seed_currencies.sql
   ```
3. Copy `config/config.example.php` to `config/config.php` and fill in your DB credentials and app settings.
4. Point your subdomain's document root directly at this folder (not a `public/` subfolder).
5. Visit the subdomain — you should see the Shamba Track welcome shell.

## Build phases

This project is being built in testable phases rather than all at once. See the phase plan in project docs/issues for the full breakdown:

- **Phase 0 — Foundation & repo setup** ✅ (this commit)
- Phase 1 — Auth (phone + OTP, persistent login)
- Phase 2 — Batch & infrastructure setup
- Phase 3 — Daily operational logging (feed, eggs, mortality, labor, medication, transport)
- Phase 4 — Vaccination & reminders system
- Phase 5 — Sales & revenue
- Phase 6 — Smart calculations engine (FCR, benchmarks, break-even, anomaly flags)
- Phase 7 — Reporting & dashboard
- Phase 8 — Offline sync hardening (conflict resolution)
- Phase 9 — Polish & localization (English/Swahili, low-literacy UI pass)

## License

TBD — public repository, license to be decided before first public release.
