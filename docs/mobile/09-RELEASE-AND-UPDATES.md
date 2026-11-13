# 09 — Builds, OTA updates and store release

The app lives in `mobile/` (Expo SDK 58, React Native 0.88, Expo Router, TypeScript).

## Day-to-day development

```bash
# 1. API (from repo root)
php artisan serve --host=127.0.0.1 --port=8741
# 2. Metro (from mobile/)
npx expo start --dev-client
# 3. First time only, or after adding a native library: build + install the dev build
npx expo run:ios --device "iPhone 16 Pro"     # needs Xcode (already on this Mac)
```

- The iOS simulator reaches the Mac's `localhost`, so no config is needed.
- For a real phone, copy `mobile/.env.example` to `.env` and set `EXPO_PUBLIC_WEB_URL` to the Mac's LAN IP or staging.
- Without Xcode / Android Studio: `eas build --profile development` builds the dev client in the cloud.

## One-time EAS setup (needs the Datasoft Expo account)

```bash
cd mobile
npx eas-cli@latest login
npx eas-cli@latest init            # writes extra.eas.projectId into app config
npx eas-cli@latest update:configure  # writes updates.url = https://u.expo.dev/<projectId>
```

## Profiles (`mobile/eas.json`)

| Profile | Who | Channel | API |
|---|---|---|---|
| `development` | Team, on device with the dev client | development | staging |
| `development-simulator` | Team, iOS simulator | development | staging |
| `preview` | Testers, installable APK / ad-hoc iOS | preview | staging |
| `production` | App Store / Play Store | production | apnainvoice.com |

## Over-the-air updates (EAS Update)

- `runtimeVersion.policy = appVersion`: an OTA update only reaches builds with the same app version.
  Changing native code (new native library, permissions, icons, app.json native fields) needs a
  **new store build and a version bump**; JS/screens/styling changes ship as OTA.
- `updates.checkAutomatically = NEVER`: the app checks itself (`src/components/update-gate.tsx`)
  on launch and on return to foreground. If an update exists it shows the **"Updating Apna
  Invoice" modal with a progress bar**, then restarts into the new version. If the download
  fails the user can retry or continue with the current version. More → "Check for app updates"
  does the same on demand.
- Publish:

```bash
npx eas-cli@latest update --channel preview --message "Fix invoice total rounding"
# after testing on preview builds:
npx eas-cli@latest update --channel production --message "…"
```

- Roll back by republishing the previous update (`eas update:republish`).

## Store compliance checklist

Built into the app now:

- [x] **Account deletion in the app** (Apple 5.1.1(v), Google Play account-deletion policy):
  More → Profile & account → Delete my account (password confirm, same `AccountErasureService` as web).
- [x] **Privacy policy and Terms** links in the app (More → Help & legal) and on sign-up
  (terms must be accepted, same as the web).
- [x] **Support / contact** link (More → Help & support).
- [x] **No tracking, no ads, no third-party analytics**; iOS privacy manifest declares name,
  email, phone and financial info collected *for app functionality only*, not linked to tracking.
- [x] `ITSAppUsesNonExemptEncryption = false` (only HTTPS) — no export-compliance prompt.
- [x] No unnecessary permissions (camera, location, contacts, microphone not requested; old
  Android storage / overlay / audio permissions blocked).
- [x] iPhone only (`supportsTablet: false`) — no iPad screenshots needed.
- [x] Token stored in Keychain / Keystore (`expo-secure-store`), HTTPS in release.
- [x] Features not yet in the app open the website instead of dead buttons; nothing is
  "coming soon" behind a disabled control.

Still to do before submission (owner / store accounts):

- [ ] Apple Developer + Google Play developer accounts (Datasoft), app records created.
- [ ] **App Review demo account** with sample data (e.g. a seeded reviewer login on production).
- [ ] App Store "App Privacy" questionnaire and Google Play **Data safety** form — answer as in the
  privacy manifest above (data collected: name, email, phone, business & invoice data; not sold,
  not used for tracking; encrypted in transit; users can delete).
- [ ] Store listing: name "Apna Invoice", subtitle, description, keywords, category Business /
  Finance, support URL `https://apnainvoice.com/contact`, privacy URL `https://apnainvoice.com/privacy`.
- [ ] Screenshots (6.9" iPhone, Android phone) from the dev build.
- [ ] Content rating questionnaire (Play), age rating (App Store) — no objectionable content.
- [ ] **API: email-code verification on `/api/auth/register`** (web verifies; API does not yet) —
  fix before public launch.
- [ ] Google sign-in (`FEATURES.googleSignIn` in `src/config.ts`) once the API has a token-exchange endpoint.
- [ ] Staging server (`staging.apnainvoice.com`) for preview builds.
