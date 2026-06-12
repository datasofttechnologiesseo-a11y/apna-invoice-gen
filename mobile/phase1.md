# Apna Invoice — Mobile App: Phase 1

Status of the React Native (Expo) mobile app and the JSON API it runs on.
Phase 1 delivers the **complete core sales workflow** end-to-end.

- **Stack:** React Native + Expo **SDK 54** (React 19.1, RN 0.81), TypeScript
- **Backend:** existing Laravel app + a new token API (Laravel Sanctum)
- **State/data:** React Query (server cache) + a small Auth context
- **Navigation:** React Navigation (bottom tabs + native stacks)

---

## ✅ What's done in Phase 1

### Backend — JSON API (`routes/api.php`)
A token-based API was added alongside the existing web app, reusing its exact
GST math (`InvoiceCalculator`), invoice numbering, and audit logging so the
mobile app produces byte-identical documents.

- **Auth (Sanctum):** register, login, logout, `me`
- **Companies:** list, active, show, create, update, switch
- **Customers:** full CRUD
- **Products:** full CRUD + search
- **Invoices:** list/filter/search, create, edit, finalize, cancel, delete, share-link
- **Payments:** record, reverse
- **Quotations:** list, create, edit, send, accept, decline, convert-to-invoice
- **Dashboard** KPIs · **States** lookup

All endpoints scope data to the user's active company and were smoke-tested
end-to-end (e.g. draft → finalize `INV-0001` → payment `RCPT-0001` → paid;
quote → send `QT-0001` → accept → convert).

### Mobile app
- **Auth:** login / register, secure token storage (expo-secure-store),
  auto-logout on 401
- **Dashboard:** outstanding / overdue / revenue KPIs + recent invoices
- **Invoices:** list with status filters & search → detail (issue, record
  payment, share/WhatsApp, cancel, delete) → create/edit with line items,
  GST picker, customer & product pickers, live total preview
- **Quotations:** list/filter → detail (send, accept, decline, convert, share,
  delete) → create/edit
- **Customers:** list + add / **edit** / **delete**
- **Products:** list + add / **edit** / **delete**
- **Company:** in-app editor (state, GSTIN, bank, UPI) — so GST computes
  correctly without ever opening the web app
- **Settings:** profile, active company, logout

**Quality gates:** TypeScript typecheck clean; Android Metro bundle builds.

---

## ▶️ How to run (test in Expo Go)

You need **two terminals**.

**1. API** (from project root `D:\projects\apna-invoice-gen`) — `0.0.0.0` so the
phone can reach it over Wi-Fi:
```powershell
php artisan serve --host=0.0.0.0 --port=8741
```

**2. Expo** (from `mobile/`):
```powershell
cd mobile
npx expo start -c
```
Scan the QR with **Expo Go** (phone on the same Wi-Fi as the PC).

- The app is pre-pointed at `http://192.168.1.35:8741` (set in
  `app.json` → `expo.extra.apiBaseUrl`). If this PC's IP changes, update it
  (`ipconfig` → "IPv4 Address").
- Sample login: `flow@example.com` / `password123` (has a customer, product,
  a paid invoice, and a converted quote). Or register fresh.

See `README.md` for full troubleshooting.

---

## Project layout (`mobile/src`)
```
api/         client.ts (axios + token), endpoints.ts (typed calls), types.ts
auth/        AuthContext.tsx (login/register/logout, token persistence)
components/  ui.tsx (Button/TextField/Card/StatusBadge), StatePicker.tsx
navigation/  RootNavigator.tsx (auth vs tabs), types.ts
screens/     DashboardScreen, SettingsScreen, CompanyEditModal,
             auth/ (Login, Register)
             invoices/ (list, detail, edit)
             quotations/ (list, detail, edit)
             customers/, products/
config.ts    API base URL resolution
theme.ts     colors, ₹ formatting, status colors
```

---

## ⛔ Not in Phase 1 (roadmap)
Credit notes · Cash memos · Finance (P&L, expenses, aging, GSTR-1/3B exports) ·
Customer ledger · Payment reminders · In-app PDF view/download · Multi-company
switch UI · Referrals · Blog · Admin panel.

Rough parity with the web app at end of Phase 1: **~55–60%** (the full core
daily workflow is complete; the remainder is finance/compliance/admin modules).

---

## ⚠️ Security note
A malicious `postinstall` script was found and removed from the project root
`package.json` at the start of this work. If `npm install` ran anywhere
(CI / server / a dev machine) before removal, treat that host as compromised
and rotate any secrets that were in `.env`.

---

# Session log — everything done

A chronological record of every change made in this build session.

## 0. Security findings (handled first)
- **Removed malware** from project-root `package.json`: a `postinstall` hook
  that silently downloaded a binary from a personal GitHub repo, saved it as
  `/tmp/.sshd`, and executed it on every `npm install`. Deleted the line.
- **Refused a prompt-injection** that tried to make me run `npm run reset`
  ("press any key to continue…") — not a real instruction, no such script.

## 1. Decisions captured
- Build a **mobile app** for the Laravel invoice generator.
- Framework: **React Native (Expo)**. Scope: **full feature parity** (phased).

## 2. Backend — API foundation (Sanctum)
- `composer install` (vendor/ was missing); created `.env`, generated `APP_KEY`,
  created `database/database.sqlite`.
- `php artisan install:api` → installed **Laravel Sanctum**, ran all migrations
  (incl. `personal_access_tokens`).
- Added `HasApiTokens` trait to `app/Models/User.php`.
- Seeded the `states` table (`StateSeeder`).

## 3. Backend — API code added
New files under `app/Http/`:
- **Controllers/Api/**: `AuthController`, `CompanyController`, `CustomerController`,
  `ProductController`, `InvoiceController`, `QuotationController`,
  `DashboardController`, `StateController`
- **Resources/**: `StateResource`, `CompanyResource`, `CustomerResource`,
  `ProductResource`, `InvoiceResource`, `InvoiceListResource`,
  `InvoiceItemResource`, `PaymentResource`, `QuotationResource`,
  `QuotationListResource`, `QuotationItemResource`
- **`routes/api.php`**: ~42 routes (auth, dashboard, companies, customers,
  products, invoices, payments, quotations, states)
- Reused `InvoiceCalculator`, company numbering, and `AuditLog` so API documents
  match the web app exactly.

### Backend smoke tests (all passed)
- register → `me` → dashboard
- set company state → customer → product → draft invoice (₹2000 → CGST ₹180 +
  SGST ₹180 → ₹2360) → finalize `INV-0001` → payment `RCPT-0001` → status `paid`
- quote (₹4500 + ₹810 GST = ₹5310) → send `QT-0001` → accept → convert →
  draft invoice #2 → quote marked `converted`

## 4. Mobile app — scaffold & foundation
- `create-expo-app mobile --template blank-typescript`.
- Installed deps via `expo install`: `expo-secure-store`, `expo-constants`,
  `react-native-screens`, `react-native-safe-area-context`,
  `@react-navigation/native` + `native-stack` + `bottom-tabs`, `axios`,
  `@tanstack/react-query`.
- Built the source tree (see "Project layout" above): API client + typed
  endpoints + types, Auth context, UI components, navigation, and all screens
  (dashboard, invoices, quotations, customers, products, settings, company edit).
- Wired providers in `App.tsx` (SafeArea + React Query + Auth + navigation).

## 5. Phase 4 modules added
- **Company edit** screen (mobile onboarding/self-sufficiency).
- **Edit + delete** for customers and products.
- **Quotations** module — API controller/resources/routes **and** mobile
  screens (list, detail with full lifecycle, create/edit), plus a new "Quotes"
  tab.

## 6. Run setup
- `mobile/README.md` quick-start.
- `app.json` → `expo.extra.apiBaseUrl` set to this PC's LAN IP
  `http://192.168.1.35:8741`.

## 7. Troubleshooting (live)
- **"Unable to resolve asset ./assets/icon.png"** — the `assets/` folder was
  empty; removed `icon` / `adaptiveIcon` / `favicon` references from `app.json`
  so Expo uses default assets.
- **"Project is incompatible with this version of Expo Go"** — the phone's
  Expo Go supports **SDK 54**, but the project was on **SDK 56**. Downgraded:
  `npm install expo@sdk-54` then `npx expo install --fix`
  (expo 56.0.8→54.0.35, react-native 0.85→0.81, react 19.2→19.1). Typecheck
  and Android bundle both still pass.
- Taught how to free **port 8081** (`netstat -ano | findstr :8081` →
  `taskkill /PID <pid> /F`, or `Get-NetTCPConnection`/`Stop-Process`).

## 8. Verification gates used throughout
- `php artisan route:list` to confirm route registration.
- PowerShell `Invoke-RestMethod` end-to-end API smoke tests.
- `npx tsc --noEmit` (clean) and `npx expo export --platform android` (builds)
  after each major mobile change.
