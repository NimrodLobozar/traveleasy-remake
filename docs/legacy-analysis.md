# Legacy analysis: the original TravelEasy

Source: `../TravelEasy` (team school project, MBO Utrecht, 2025; team of 4–5).
This document records **what the original did** (so the remake keeps all functionality) and
**what went wrong** (so the remake doesn't repeat it). It's a reference and won't be updated after the analysis.

## 1. What it was

A web app for a fictional travel agency. Visitors search and browse flights/trips and offers;
logged-in staff manage customers, bookings, invoices, trips and messages from a dashboard.

Stack: Laravel 11 (PHP 8.2), Blade, Breeze auth, Alpine.js, Tailwind (loaded **three ways**: Vite v4,
CDN v2.2 and the Play CDN), MySQL 8 with stored procedures, Flatpickr, Hugging Face (flan-t5) chatbot,
Docker (PHP 8.3 + Apache, MySQL 8), deployed to CasaOS via Portainer (stack from Git repo).

## 2. Functional inventory (keep all of this, or improve it)

### Public
| Area | Original route | What it did |
|---|---|---|
| Home / search | `/` | Search form: departure country → destinations reachable from it → available dates (Flatpickr), travellers (adults 18+, children 2–17). Hero banner with lorem ipsum. |
| Trips | `/trips` | Trip list with date filtering |
| Offers | `/aanbiedingen` | Trips with discount code, % discount, valid from/until |
| Destinations, Packages | `/destinations`, `/packages` | **Static** placeholder pages |
| About, Contact | `/about`, `/contact` | Contact form that **sends nothing** (just flashes "sent") |
| Chatbot | `/chat` | Travel assistant via Hugging Face flan-t5-large, NL/EN |
| Auth | Breeze | Register, login, forgot/reset password, verify e-mail, profile edit/delete |

### Back office (any logged-in user, see §4)
| Area | Original route | What it did |
|---|---|---|
| Dashboard | `/dashboard` | User name, links, booking statistics |
| Accounts | `/account` | CRUD of customer = person + customer + contact (stored procedures) |
| Customers | `/customers` | **Second, overlapping** CRUD of the same customers |
| Bookings | `/bookings` | CRUD: customer, trip, seat, purchase date/time, status, price, quantity, special requests; search by date/time/destination |
| Invoices | `/invoice` | CRUD: number, date, excl./incl. VAT, VAT, status, linked to booking |
| Trip overview | `/reisoverzicht` | CRUD of trips incl. departure/arrival airport, dates, times, flight number, status |
| Departures | `/departures` | Departure airport list |
| Messages | `/messages` | Customer ↔ staff conversations: start conversation, reply, mark read, delete conversation, delete last message |

### Data model (original)
```
people ─┬─ users ── roles (hasOne, string 'Admin')
        ├─ customers ── contacts (address, mobile, email ← used as login!)
        └─ employees (Manager | Administrator | Desk Employee)
departures (country, airport)   destinations (country, airport)
trips (employee, departure, destination, flight_number, dep/arr date+time, status)
bookings (customer, trip, seat, purchase date/time, status, price, quantity, special_requests)
invoices (booking, number, date, excl/vat/incl, status)
offers (trip, offer_code, discount %, valid_from/until)
conversations (user) ── messages (user, content, is_read)
```
Every table also had `is_active` + `note`.

Test accounts in the seeder: `admin@gmail.com / Admin1234`, `test@gmail.com / Test1234`, plus 300 fake users.

## 3. Problems to fix in the remake

### Security & privacy (most important)
- **No authorization at all.** Every logged-in user, including a normal customer, could open all accounts,
  customers, invoices, bookings and every conversation. Admin role was only used to hide nav links, and
  checked three different ways (`roles->pluck('role')`, `role === 'admin'`, ...).
- **Passport details** stored as plain text, with no encryption and no purpose limitation.
- Login e-mail lived in the `contacts` table and was resolved by a hand-written `UserProvider`. That's fragile,
  and staff without a customer record couldn't log in normally.
- CSRF disabled for the chatbot endpoint; the HTTP client used `withoutVerifying()` (TLS verification off).
- User prompts were sent to a third-party AI with no notice or consent.
- Default DB passwords in compose (`root`, `traveleasy`), demo credentials published in the README.
- "Developer" checkboxes in production forms (`dev_connection`, `simulate_error`) to fake errors for school
  unhappy-path demos.
- No rate limiting outside the Breeze defaults, no security headers, no audit log of who viewed or changed customer data.
- No GDPR features: no data export, no account-deletion flow covering all personal data, no retention policy,
  no privacy statement.

### Domain & data
- `spCreateReis` **inserted a new departure and destination row for every trip**, so airports were
  duplicated endlessly. Airports should be reference data (IATA code, name, city, country).
- Trips had **no price**; booking price was typed in by hand. Offers were never applied to a price.
- Accounts and Customers were two CRUDs over the same data.
- Booking search filtered on `destinations.name`, a column that doesn't exist.
- Money as `decimal(8,2)` with VAT computed by hand in forms; no single source of truth for VAT rate.
- No booking flow for customers at all: only staff could create bookings.
- Messages: hard limit of **25 characters**, conversation list not scoped to the user.
- Stored procedures + Eloquent + factories mixed with no clear rule; logic split between SQL and PHP.
  Procedures used `COLLATE utf8mb4_0900_ai_ci` hacks and `--log-bin-trust-function-creators`.
- Duplicate route definitions (resource + manual), so `route:cache` couldn't run.

### Design, UX & accessibility
- Visual style inconsistent between pages (dark back office, light public pages), lorem ipsum in hero.
- Mixed Dutch and English UI text, with no i18n.
- Error states built for the school's assessment, not for users.
- Images: large unoptimised JPG/PNG, odd file names, no `alt` strategy, no responsive images.
- No skip links, no focus management, contrast never checked; JS-only interactions (toggle buttons) with no
  ARIA state.

### Engineering
- Only Breeze's default tests; nothing for the domain.
- No CI, no linting enforced, no seed/demo separation, `minimum-stability: dev`.
- Docker image built on the home server by Portainer (slow, no versioned images, no rollback).

## 4. Things that worked and are worth keeping
- Docker Compose + Portainer "stack from Git" deployment to CasaOS.
- Entrypoint that waits for the DB, migrates and seeds only on an empty DB.
- The dependent search (departure → reachable destinations → available dates) is a good UX idea.
- NL/EN awareness (chatbot answered in the chosen language).
