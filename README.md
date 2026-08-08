# MeroMaidan - Nepal's Smart Sports Venue Booking Platform

A full-stack multi-tenant SaaS web application for discovering and booking sports venues across Nepal. Built with PHP, MySQL, and vanilla JS.

## 🚀 Features

- **Player Portal** – Search, filter, book venues, manage bookings, favorites, reviews, notifications
- **Owner Portal** – Venue CRUD, slot pricing, field operations, staff roles, CRM, promotions, subscription management
- **Super Admin Panel** – Governance dashboard, tenant management, SaaS plan config, audit logs, CMS, review moderation
- **Mock eSewa Payment Gateway** – Realistic Nepali payment flow with invoice generation
- **Interactive Map** – Leaflet.js powered venue discovery map
- **Notification System** – In-app notifications across all user roles

## 🛠️ Tech Stack

- **Backend:** PHP (Procedural) + MySQL (PDO)
- **Frontend:** HTML5, Vanilla CSS, Vanilla JavaScript
- **Map:** Leaflet.js + OpenStreetMap
- **Server:** Apache (XAMPP)

## 📦 Setup

1. Install [XAMPP](https://www.apachefriends.org/)
2. Clone this repo into `C:\xampp\htdocs\mm`
3. Start Apache and MySQL in XAMPP
4. Import `meromaidan_full.sql` via phpMyAdmin **OR** run `php db/seed_complete.php`
5. Visit `http://localhost/mm`

## 🔐 Test Credentials (Password: `Admin@1234`)

| Role | Email |
|------|-------|
| Player | anil@example.com |
| Owner | ramesh@royalfutsal.com |
| Super Admin | admin@meromaidan.com |

## 📁 Project Structure

```
mm/
├── api/            # REST API endpoints (booking, search, reviews, notifications)
├── assets/         # CSS & JS files
├── auth/           # Login, register, logout
├── db/             # Database seeder & migrations
├── esewa/          # Mock eSewa payment gateway
├── owner/          # Venue owner dashboard
├── player/         # Player dashboard
├── superadmin/     # Super admin governance panel
├── index.php       # Public homepage & venue marketplace
├── venue.php       # Venue detail & booking page
└── list-ground.php # Owner onboarding form
```

## 📄 License

MIT License © 2026 MeroMaidan
