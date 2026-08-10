# MeroMaidan - Nepal Sports Venue Marketplace

MeroMaidan is a multi-tenant PHP and MySQL application for discovering, booking, and operating sports venues across Nepal.

## Product areas

- Player portal: search, booking, favourites, payments, invoices, and notifications
- Venue Owner portal: one-venue management, slots, pricing, operations, staff, reports, subscription, and promotions
- Super Admin portal: tenant governance, commercial service configuration, dedicated Recommended Venue management, Event Promotion moderation, payments, audit logs, and CMS
- Marketplace: swipeable CMS/event hero, organic venue discovery, clearly labelled Recommended Venues, and venue booking
- Mock eSewa gateway: local booking, subscription, and promotional payment testing

MeroMaidan intentionally has no customer feedback, review, or rating feature.

## Commercial model

There are exactly three separate commercial services:

- Annual Venue Subscription: NPR 9,999/year for one venue
- Recommended Venue: NPR 1,000/month for clearly labelled location-based visibility
- Event Promotion: NPR 2,000 for one seven-day 1600×600 hero campaign, with an optional venue coupon and Super Admin approval

Recommended placement and Event Promotion are optional advertising services. They are not subscription tiers and do not modify organic search ranking.
Recommended Venue orders are purchased by venue owners through the mock eSewa gateway only. Super Admin can edit and moderate submitted orders but cannot manually manufacture a successful payment.

## Technology

- PHP with PDO
- MySQL
- HTML, CSS, and vanilla JavaScript
- Leaflet and OpenStreetMap
- Apache through XAMPP

## Setup

1. Install XAMPP.
2. Put the project at `C:\xampp\htdocs\mm`.
3. Start Apache and MySQL.
4. For a clean installation, import `meromaidan_full.sql` or run `C:\xampp\php\php.exe db/seed_complete.php`. Both include the current commercial model and cleanup.
5. Only when upgrading an older database, apply `db/migration_v3_cms.sql`, `db/migration_v4_business_model.sql`, `db/migration_v5_remove_feedback_legacy_promotions.sql`, and `db/migration_v6_remove_featured.sql` in that order.
6. Open `http://localhost/mm`.

## Test credentials

All seeded test users use password `Admin@1234`.

| Role | Login page | Email |
|---|---|---|
| Player | `/auth/login.php` | `anil@example.com` |
| Venue Owner | `/auth/owner-login.php` | `ramesh@royalfutsal.com` |
| Super Admin | `/auth/admin-login.php` | `admin@meromaidan.com` |

## Main folders

```text
api/          JSON endpoints and shared backend logic
assets/       CSS and JavaScript
auth/         separated player, owner, and admin authentication
db/           schema, seeders, and migrations
esewa/        local mock payment workflow
owner/        venue-owner workspace
player/       player workspace
superadmin/   platform governance workspace
```

## License

MIT License, 2026 MeroMaidan.
