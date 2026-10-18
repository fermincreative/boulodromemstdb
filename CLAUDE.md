# Ledenadmin — Boulodrôme Maastricht

Member administration for **Boulodrôme Maastricht**, a small Dutch non-profit (~265 members).
Built with **Laravel + Filament** on **SQLite**, hosted on ordinary shared PHP hosting. Cost matters: no paid services unless agreed.

This project replaces an Excel member list (`Ledenlijst 2026.xlsx`) and a stand-alone PHP card tool (`ledenpas`).

## Status
**Phase 1 done** (Laravel 13, Filament 5.10, Pest 4, installed in the repo root so `public/` matches the Forge site).
**Phase 2:** Leden resource done; import command still to do.
**Phase 4 (payments) mostly done:** rates (Beheer → Tarieven: `settings.annual_fee_cents` + `joining_rates` by MM-DD), `App\Services\Fees`, payments (Betalingen page + tab per member), Markeer als betaald (single/bulk) → memberships active → card queued for the latest period. A payment can cover several memberships (`memberships.payment_id`) because a December sign-up pays €10 + next year's €90 in one go — this deviates from the domain model below on purpose.
Decision: memberships follow the **calendar year** (open decision resolved 2026-10-18).
**Phase 3 done:** `App\Services\MembershipCard` (layout measured from `examples/*.pdf`; `ledenpas` source was not available), verify page at `/verify` and `/verify.php`. The `memberships` table + Lidmaatschappen tab were built early because the verify page needs them. Payments still to do.
Mail: Amazon SES wired up (`aws/aws-sdk-php`, `SES_*` env keys, global reply-to); check settings with `php artisan mail:test <adres>`.
E-mail texts: `mail_templates` table (keys: membership_card, welcome, payment_confirmation, cancellation; edited under Beheer → E-mailteksten; `{placeholders}` per key in `App\Services\MailTemplates`). Card, aanmelding (opens after creating a member with an open payment) and opzegging are editable in their dialog; betalingsbevestiging is only a checkbox (default on) when marking paid. Sent via `SendMemberMail` / `SendMembershipCard` jobs, logged. Bank details: settings `bank_iban`, `bank_account_holder` (Tarieven page).
Member numbers are shown as `SBM-0261-91` (`App\Support\MemberNumber`, MOD 97-10 check digits, prefix `MEMBER_NUMBER_PREFIX`) via `$member->display_number`. The stored `member_number` stays the plain integer and the QR keeps signing it (old cards stay valid).
Card year badge colours per year: `card_year_styles` table (Beheer → Kaartkleuren, admins); default = accent with black text. 2027 = white on black.
Public sign-up form: `/aanmelden` (`SignUpController`, `SignUpRequest`, `App\Actions\Members\SignUpMember`) → same flow as the panel (member + pending membership + open payment) + Aanmelding e-mail + notice to `SIGNUP_NOTIFY_ADDRESS`/reply-to. Spam: honeypot `website`, encrypted `form_token` min. 4 s, rate limiter `signup` (5/hour/IP; nginx already restores the Cloudflare visitor IP). Switch off with `SIGNUP_ENABLED=false`.
Dates are stored as `Y-m-d H:i:s` in SQLite: compare dates with `whereDate`, not `where`/`unique`.
Roles are a `role` column + `App\Enums\UserRole` (no permissions package). Admin users: `php artisan users:create`. Backups: `php artisan db:backup`.

## Stack & setup
- PHP 8.3+, Laravel (current stable), Filament (current stable major, v4 or newer). Check the versions with Composer; don't assume.
- Database: SQLite (`database/database.sqlite`). Enable WAL mode and foreign keys.
- Queue: `database` driver. Scheduler + queue run from a single cron on shared hosting:
  `* * * * * php artisan schedule:run` and schedule `queue:work --stop-when-empty` every minute.
- Mail: Laravel mail config. Production will be Amazon SES (`ses` mailer) or Microsoft 365. Local: `log` mailer.
- PDF/QR: `tecnickcom/tcpdf` (6.x) — the existing card layout uses it.
- Tests: Pest. Run `php artisan test` before calling something done.
- Formatting: Laravel Pint (`./vendor/bin/pint`).

Setup steps (Phase 1):
```
composer create-project laravel/laravel ledenadmin
cd ledenadmin
composer require filament/filament
php artisan filament:install --panels      # panel id: admin, path: /admin
touch database/database.sqlite             # DB_CONNECTION=sqlite in .env
php artisan migrate
php artisan make:filament-user
```

## Conventions
- **Code, database tables and columns: English. Everything users see: Dutch.** Admin users are Dutch volunteers; members get formal "u".
  Set `APP_LOCALE=nl`, use Filament's Dutch translations, and label fields in Dutch (e.g. `last_name` → "Achternaam").
- Dates are shown as `d-m-Y`. Timezone `Europe/Amsterdam`.
- Money is stored as integer cents.
- Business logic goes in actions/services (`app/Actions`, `app/Services`), not in Filament resources. Filament only calls them.
- Every state change that matters (payment, renewal, card sent, cancellation) is logged (consider `spatie/laravel-activitylog`).
- Keep dependencies few; ask before adding paid or heavy packages.

## Security & privacy (GDPR)
The database holds names, addresses, phone numbers and birthdays.
- **MFA for admin users** (Filament's built-in app/TOTP MFA; e-mail codes as fallback). Keep Filament updated: several MFA bugs were fixed in 2026.
  Decision 2026-10-18: MFA is **optional for now** (users can enable it on their profile). Enforce it with `ADMIN_MFA_REQUIRED=true` in `.env`.
- Roles: `admin` (everything), `treasurer` / penningmeester (payments, renewals), `viewer` (read-only). Enforce them with policies.
- Never commit `.env`, the SQLite file, member exports or the original spreadsheet. The repo must stay private.
- Nightly backup with `sqlite3 database.sqlite ".backup ..."` (or `VACUUM INTO`), kept off the server. Never put the live DB in Dropbox/OneDrive.

## Domain model
```
members
  id
  member_number      unique, permanent for life (the existing LidID — keep the existing numbers on import)
  first_name, middle_name (tussenvoegsel), last_name
  email (nullable, may be shared by two members — e.g. couples), phone_mobile, phone_home
  street, postal_code, city, country
  birthday (nullable)
  club               (Vereniging: SBM Halpas, Mosa Petanca, JBC Amby, Geen, …) — consider a `clubs` table
  contact_for        (Contactpersoon van, nullable)
  joined_at, cancelled_at (nullable)
  notes

memberships          one row per period; never overwrite history
  id, member_id
  starts_at, ends_at
  status             pending | active | expired | cancelled
  card_sent_at (nullable)
  unique (member_id, starts_at)

payments
  id, membership_id
  amount_cents, status (open | paid | cancelled | refunded)
  method             cash | bank_transfer | ideal (later)
  paid_at, reference (nullable; e.g. Mollie id)
```
A member's "current expiry" is the `ends_at` of their latest active membership — don't store it separately.

## Business rules
1. **New member** → create a member + first membership + open payment. When paid → membership active → card generated and e-mailed.
2. **Renewal**: a daily scheduled command finds active memberships ending within **30 days** that have no follow-up membership yet,
   and creates the next period (`status = pending`) plus an open payment for the current annual fee. It can e-mail the member a reminder.
   Must be idempotent: running it twice creates nothing extra.
3. **Payment paid** (treasurer clicks "Markeer als betaald", or later a Mollie webhook) → membership becomes active,
   the new period starts at the **old end date** (not the payment date) → card generated and e-mailed → `card_sent_at` set.
4. Cancelled members (`cancelled_at`) get no renewals and no cards.
5. Members without an e-mail address are skipped for e-mail but can still have a print card downloaded.
6. Annual fee lives in settings (config or a settings table), not hard-coded.

**Decided (2026-10-18): calendar year.** All memberships end 31-12. New members pay a joining rate by sign-up date (editable under Tarieven); from 1 December they pay the rest of the year plus next year in one payment.

## Membership card (port from `ledenpas`)
Port `src/Card.php` from the existing `ledenpas` project into `app/Services/MembershipCard.php`. Keep the look:
- Two PDFs per member: **phone** (70 × 150 mm page, large QR, "Toon deze code bij de ingang") and **print** (A4, card 54 × 85.6 mm with cut marks).
- Header black `#000000`, accent `#E5D0A7` (header text, lines, year badge). Font DejaVu Sans.
- Logo (`logo.jpg`, black on white) as an ~8% watermark below the QR; white made transparent first (GD).
- Content: association name "Boulodrôme Maastricht", "LIDMAATSCHAPSKAART", QR, full name, club (hide "Geen"/"Onbekend"),
  LIDNUMMER, JAAR on an accent badge (with a clear gap below the "JAAR" label), "Geldig t/m <ends_at>".
- **QR must stay compatible** with cards already sent: payload signed with
  `strtoupper(substr(hash_hmac('sha256', "{number}|{full name}|{year}", QR_SECRET), 0, 10))`; keep the same secret (`QR_SECRET` in `.env`).
  With a verify URL configured the QR is `…/verify?l=&n=&j=&c=`; port the public green/red verify page as a Laravel route.
- E-mail (Dutch, formal): subject "Uw lidmaatschapskaart {jaar} — Boulodrôme Maastricht", both PDFs attached, explain phone vs print version.
  Send through a queued Mailable.

## Filament admin
- **Leden** resource: table with search (name, e-mail, number), filters (club, active / expiring within 30 days / awaiting payment / cancelled),
  relation managers for memberships and payments.
  Row actions: "Kaart (telefoon)", "Kaart (print)", "Kaart versturen", "Opzeggen".
- **Betalingen**: list of open payments with a "Markeer als betaald" action (treasurer), plus a bulk version.
- Dashboard widgets: active members, renewals awaiting payment, expiring within 30 days, cards not yet sent.
- Bulk action: download print cards as ZIP.

## Import (one-off)
Artisan command `members:import {path}` reading the old spreadsheet (sheet "Leden", table "Tabel1"). Columns:
`LidID, Voornaam, Tussen, Achternaam, Adres, Postcode, Woonplaats, Land, Tel Mobiel, Tel Thuis, E-mailadres, Verjaardag, Vereniging, Mail verstuurd, Betaald, Opgezegd op, Contactpersoon van`.
Known quirks:
- LidID is an Excel formula and is pre-filled in empty rows → skip rows without a first or last name.
- Some postcodes are numbers (Belgian, e.g. `3620`); keep them as strings.
- Birthdays are Excel dates.
- Shared e-mail addresses are expected; don't de-duplicate on e-mail.
Create an active membership for the current year for everyone imported. Use `--dry-run` to show a summary first.

## Phases (confirm with the user between phases)
1. Laravel + Filament + SQLite set up, admin login with MFA, roles.
2. Members resource + import command (test with the real spreadsheet locally, never commit it).
3. Card service ported + preview/download actions + verify page.
4. Memberships & payments, "mark as paid" → card e-mail.
5. Scheduled renewals + reminder e-mails (after the open decision above).
6. Later / optional: Mollie iDEAL payments with webhook, member self-service page.

## Definition of done
- Tests for the business rules (renewal idempotency, period dates, payment → activation → card queued).
- `php artisan test` and Pint pass.
- Dutch labels everywhere users look; no member data in the repo.