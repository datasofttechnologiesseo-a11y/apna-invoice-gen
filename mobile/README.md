# Apna Invoice — Mobile App (Expo / React Native)

Cross-platform mobile client for the Apna Invoice Laravel backend. Talks to the
JSON API (`routes/api.php`) using Sanctum token auth.

## Open it in Expo Go

You need **two things running at once**: the Laravel API and the Expo dev server.

### 1. Start the API so your phone can reach it

From the project root (`D:\projects\apna-invoice-gen`):

```powershell
php artisan serve --host=0.0.0.0 --port=8741
```

`--host=0.0.0.0` is important — it makes the API reachable from other devices on
your Wi-Fi (not just `localhost`). Your phone and PC must be on the **same Wi-Fi**.

> The app is pre-configured to call `http://192.168.1.35:8741` (this PC's LAN IP,
> set in `app.json` → `expo.extra.apiBaseUrl`). If your PC's IP changes, update
> that value. Find it with `ipconfig` (look for "IPv4 Address").

### 2. Start Expo and open on your phone

```powershell
cd mobile
npx expo start
```

- Install **Expo Go** from the Play Store / App Store.
- Scan the QR code shown in the terminal with Expo Go (Android) or the Camera app (iOS).
- The app loads over Wi-Fi.

### Emulator / simulator (no physical phone)

- Android emulator: press `a` in the Expo terminal. (The app falls back to
  `10.0.2.2` automatically if you clear `apiBaseUrl`.)
- iOS simulator (macOS only): press `i`.

## First run

1. Tap **Create an account** → register. A company is created for you automatically.
2. Go to **Settings** to confirm the company; add your **state** etc. later via the
   web app (mobile company-edit is on the roadmap).
3. **Customers** → add a customer (state is required for GST).
4. **Products** → add a product.
5. **Invoices → + New** → pick customer, add line items → **Save draft** →
   open it → **Issue invoice** → **Record payment**.

## Troubleshooting

- **"Cannot reach the server"** — API not running, wrong IP in `app.json`, or phone
  on a different network. Re-check `ipconfig` and that you used `--host=0.0.0.0`.
- **Stuck on splash** — a stored token failed to validate; it auto-clears and drops
  to the login screen.

## Scripts

```powershell
npm start            # expo start
npm run android      # expo start --android
npx tsc --noEmit     # typecheck
```
