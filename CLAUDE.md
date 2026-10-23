# Ledenadmin — Boulodrôme Maastricht

Member administration for **Boulodrôme Maastricht**, a small Dutch non-profit (~265 members).
Built with **Laravel + Filament** on **SQLite**, hosted on ordinary shared PHP hosting. Cost matters: no paid services unless agreed.

This project replaces an Excel member list (`Ledenlijst 2026.xlsx`) and a stand-alone PHP card tool (`ledenpas`).

## Status
**Phase 1 done** (Laravel 13, Filament 5.10, Pest 4, installed in the repo root so `public/` matches the Forge site).
**Phase 2:** Leden resource done; import command still to do.
**Phase 4 (payments) done**, incl. Mollie iDEAL (see below): rates (Beheer → Tarieven: `settings.annual_fee_cents` + `joining_rates` by MM-DD), `App\Services\Fees`, payments (Betalingen page + tab per member), Markeer als betaald (single/bulk) → memberships active → card queued for the latest period. A payment can cover several memberships (`memberships.payment_id`) because a December sign-up pays €10 + next year's €90 in one go — this deviates from the domain model below on purpose.
Decision: memberships follow the **calendar year** (open decision resolved 2026-10-18).
**Phase 3 done:** `App\Services\MembershipCard` (layout measured from `examples/*.pdf`; `ledenpas` source was not available), verify page at `/verify` and `/verify.php`. The `memberships` table + Lidmaatschappen tab were built early because the verify page needs them. Payments still to do.
Mail: Amazon SES wired up (`aws/aws-sdk-php`, `SES_*` env keys, global reply-to); check settings with `php artisan mail:test <adres>`.
E-mail texts: `mail_templates` table (keys: membership_card, welcome, payment_confirmation, cancellation, renewal; edited under Beheer → E-mailteksten; `{placeholders}` per key in `App\Services\MailTemplates`). Card, aanmelding (opens after creating a member with an open payment) and opzegging are editable in their dialog; verlenging too, in the bulk Verlengen / Betaallink opnieuw mailen dialogs, where the text keeps its `{placeholders}` and is filled in per member; betalingsbevestiging is only a checkbox (default on) when marking paid. Sent via `SendMemberMail` / `SendMembershipCard` jobs, logged. Bank details: settings `bank_iban`, `bank_account_holder` (Tarieven page).
Member numbers are shown as `SBM-0261-91` (`App\Support\MemberNumber`, MOD 97-10 check digits, prefix `MEMBER_NUMBER_PREFIX`) via `$member->display_number`. The stored `member_number` stays the plain integer and the QR keeps signing it (old cards stay valid).
Card year badge colours per year: `card_year_styles` table (Beheer → Kaartkleuren, admins); default = accent with black text. 2027 = white on black.
Public sign-up form: `/aanmelden` (`SignUpController`, `SignUpRequest`, `App\Actions\Members\SignUpMember`) → same flow as the panel (member + pending membership + open payment) + Aanmelding e-mail + notice to `SIGNUP_NOTIFY_ADDRESS`/reply-to. Spam: honeypot `website`, encrypted `form_token` min. 4 s, rate limiter `signup` (5/hour/IP; nginx already restores the Cloudflare visitor IP). Switch off with `SIGNUP_ENABLED=false`.
**Clubs & who pays (2026-10-22):** `clubs.pays_collectively` (Mosa Petanca, JBC Amby): their members never get an individual payment; their periods stay pending with `payment_id = null` until "Activeren (betaald door vereniging)" (`ActivateClubPaidMembership`). `clubs.is_signup_club` (SBM Halpas): everyone who signs up via `/aanmelden` joins that club (no club choice on the form). Both are toggles under Beheer → Verenigingen. Association members are shown with a blue badge (`App\Filament\Tables\ClubColumn`).
**Renewals (phase 5, decided 2026-10-22: manual bulk, no daily scheduled command):** Verlengingen page (`RenewalResource`, tabs Te verlengen / Wacht op betaling / Wacht op vereniging / Actief; year = next year from October). "Te verlengen" = membership ends within `settings.renewal_window_days` (default 42, Beheer → Tarieven) or moved up by hand (`members.renew_early_for` = year, `MoveUpForRenewal`); the rest is under "Nog niet aan de beurt". Scopes: `renewable` (eligible, used for idempotency), `dueForRenewal`, `notYetDueForRenewal`. Tick members → "Verlengen" → `RenewMembership` (idempotent; period 1-1..31-12, open payment for the annual fee + "Verlenging" e-mail with iDEAL link, or for club-paid members: pending, no payment, no e-mail). "Betaallink opnieuw mailen" is the reminder.
**Mollie / iDEAL:** plain HTTP client (`App\Services\Mollie\Mollie`, no SDK), `MOLLIE_KEY`, `MOLLIE_METHODS` (default `ideal`). Every open payment has a signed public page `/betalen/{payment}` (`PaymentLink`, `{betaallink}` in welcome/renewal texts) with an iDEAL button + bank details; the page itself creates nothing (mail link scanners). Webhook `/mollie/webhook` (CSRF-exempt) and the return page both call `SettleOnlinePayment`, which re-fetches the status from Mollie and runs `MarkPaymentPaid` once (cache lock). Paid-but-cancelled or wrong amounts are only logged (`online_payment_unmatched`). Sign-up redirects straight to the checkout when Mollie is configured. Tests never hit Mollie (`Http::preventStrayRequests`, `molliePayment()` fixture in `tests/Pest.php`).
**Rooster (2026-10-22, groundwork for reservations):** `schedule_slots` = parts of a day the Boulodrôme is open (no row = closed): period `afternoon` / `evening` (standard times in settings `schedule_afternoon_times` / `schedule_evening_times`, Beheer → Openingstijden, `App\Services\OpeningHours`) or `custom` (extra part of a day with own times, several per day). Every row stores its own `starts_at`/`ends_at` ("HH:MM"): changing the standard times only affects slots opened afterwards. Parts of a day may not overlap (touching is fine), so an extra 12:00–14:00 blocks a 13:00–17:00 middag that day. Per slot `lanes` (default/max `config('schedule.lanes')` = 16), optional event (`event_name`, `event_name_public` default **off** — use `publicEventName()` anywhere non-admins look, it returns "Evenement" for private names; `event_lanes` default all lanes, `remaining_lanes_reservable` default **off**); `ScheduleSlot::reservableLanes()` is what reservations will use. Saved only through `App\Actions\Schedule\SaveScheduleSlot`. The past is locked there: nothing can be opened/changed/closed before today or for a part of today that has ended (`SaveScheduleSlot::hasEnded`). Times are typed in 24-hour notation (`App\Filament\Forms\TimeInput`, no browser time picker, which may show AM/PM). Page `App\Filament\Pages\Schedule` (/admin/rooster): week view Mon–Sun; admins click a part of the day (`editSlot` action, arguments date/period/id) or "+ Extra dagdeel"; others read-only. Logged under log name `schedule`. **Repeated events:** `App\Actions\Schedule\ScheduleSeries` creates one row per occurrence (weekly or every other week, max 12 months), linked by `schedule_slots.series_id`; dates with another event in that part of the day or an overlap are skipped and reported, never overwritten; one log entry per repeat. Editing an occurrence changes only that date unless "Ook toepassen op de volgende herhalingen" (`applyToFollowing`); an occurrence that stops being an event leaves the series. The form goes through `EditScheduleSlot` (one transaction).
**Ledenportaal (2026-10-23):** members log in on the main domain (`/`, `MemberPortalController`) with a link by e-mail — no passwords. `member_accounts` (one per e-mail address; guard `member`, separate from staff `users`/`web` in /admin) ← `members.member_account_id` (couples sharing an address share one account). Accounts are created/linked automatically (`App\Actions\Accounts\LinkMemberAccount`: on member create, on e-mail change, on login request) — no invitations. Login links: `member_login_tokens` (SHA-256 hash only, single use, 30 min, `SendLoginLink` / `LogInWithLink`, pruned daily by `model:prune`); opening the link shows a confirm button (mail scanners must not use it up); the request page never reveals whether an address is known; rate limiter `member-login` (5/hour per address, 20/hour per IP). Logged in: own members only (always through the account, never by id), status, open payments (`PaymentLink`), card download (`/mijn/kaart/{membership}/{variant}`, `Membership::cardAvailable()`), contact details editable via "Wijzigen" (`UpdateContactDetails`: phones/address saved at once, logged with the member account as causer; a new e-mail address only after confirming a signed 48 h link sent to it, `ConfirmEmailChange` — the address is the login). Name, number, birthday stay admin-only. Member logout leaves a staff login in the same browser alone. Members also see the Rooster at `/rooster` (`MemberPortalController::schedule`, this week onwards, list per day; events via `publicEventName()` + free lanes from `reservableLanes()`). Logged under log name `accounts`.
SQLite runs with `transaction_mode` IMMEDIATE (config/database.php): writes queue instead of failing with "database is locked". When member logins get busy: move sessions/cache to Redis (installed on the server) via `.env`.
**Languages (groundwork 2026-10-23):** public pages (sign-up, thanks, payment page, ledenportaal, login and e-mail-change mails) take every text from `lang/nl/{signup,portal,payment}.php` via `__()` — never hard-code Dutch in those views/controllers. `SetPublicLocale` (alias `public.locale`, only on public routes) picks from `config('app.public_locales')` (`PUBLIC_LOCALES`, now `nl` only): `?lang=` (kept in session, ignored by signed-URL checks), then browser preference, then the first enabled. Switcher appears in the header when >1 language. `APP_FALLBACK_LOCALE=nl`, so untranslated texts show in Dutch. To add English: copy the three files to `lang/en/`, translate, set `PUBLIC_LOCALES=nl,en`; a test checks the new files have every key. Not yet translatable: editable mail templates (one text each), payment descriptions/fee summaries from the database, status labels from enums, the verify page. The admin panel stays Dutch.
**Card check for staff (2026-10-23):** the QR still opens the public `/verify` page (old cards keep working). Staff logged in to /admin in the same browser (`User::loggedInStaff()`, guard `web`) also get a "Beheer" block (`VerifyCardController::manage`): contact details, open payments, links to view/edit in the panel; by role via the existing policies: "Contant ontvangen" for an open payment and "Verlengen voor {jaar} en contant ontvangen" (`App\Actions\Payments\CollectAtEntrance`, reference "Contant bij de ingang", normal MarkPaymentPaid flow) for treasurer/admin, edit link for admin. Routes `/kaartcontrole/*`; return addresses are restricted to /verify. "Beheerder? Inloggen" stores the scanned URL as intended and goes to the normal admin login. The page is `no-store` because staff see more than the public on the same URL.
Dates are stored as `Y-m-d H:i:s` in SQLite: compare dates with `whereDate`, not `where`/`unique`.
Roles are a `role` column + `App\Enums\UserRole` (no permissions package). Admin users: `php artisan users:create`. Backups: `php artisan db:backup`.

## Documentation
Technical docs live in `docs/` (start at `docs/README.md`). `docs/data-model.md` explains every table and the rules behind it: update it in the same change as any migration that adds or changes a table or column.

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
5. Renewals + reminder e-mails — done as a manual bulk screen (Verlengingen) instead of a daily command.
6. Later / optional: member self-service page.

## Definition of done
- Tests for the business rules (renewal idempotency, period dates, payment → activation → card queued).
- `php artisan test` and Pint pass.
- Dutch labels everywhere users look; no member data in the repo.