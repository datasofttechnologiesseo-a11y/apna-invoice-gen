# 08 — App Engineering Rules

Non-negotiables for the `mobile/` code. They exist so the app is fast, never fights the
keyboard, and a design or business-rule change is made **in one place**.

## 1. DRY — one source for everything

| Concern | Single source | Never |
|---|---|---|
| Business rules (GST, rounding, numbering, statuses, roles) | **Laravel services** (`app/Services/*`) via the API | Re-implemented in the app (a live total preview is the only exception, and it's replaced by the server value on save) |
| Validation messages | API 422 `errors` shown under the field | Hard-coded copies of server rules (zod only checks shape/required) |
| Colours, type, spacing, radius, shadows | `src/theme/tokens.ts` (matches `docs/mobile/design/README.md`) | A hex or font size inside a screen |
| UI building blocks | `src/components/ui/*` — `Screen`, `Header`, `Card`, `ListRow`, `StatusPill`, `Money`, `Button`, `TextField`, `Select`, `Sheet`, `EmptyState`, `TabBar` | One-off styled views copied between screens |
| Domain blocks | `src/features/<module>/components` — e.g. one `LineItemsEditor` for invoices, quotations, purchases and cash memos; one `PartyPicker` for customers and vendors; one `ReportScreen` for all 15 reports | A second copy per module |
| API calls & types | `src/api/endpoints/<module>.ts` + `src/api/types/<module>.ts` | `fetch` inside a screen |
| Server state | TanStack Query hooks `use<Module>()` in `src/features/<module>/hooks.ts` | Screen-level `useEffect` fetching |
| Money / dates / GSTIN | `src/lib/money.ts` (₹ + lakh grouping), `dates.ts`, `gstin.ts` | Inline `toLocaleString` |
| Copy / labels | `src/i18n/en.json`, wording copied from the web | Strings in JSX |

Changing the brand colour = one token. Changing how a status looks = `StatusPill`. Changing a
GST rule = Laravel only.

## 2. Screen scaffold — every screen uses `<Screen>`

`<Screen>` gives, once, for all screens:
- **Safe area** (`react-native-safe-area-context`) — content never under the notch, home
  indicator or tab bar; the gradient header extends under the status bar.
- **Top bar** — `Header` with back/close, title, optional subtitle and right actions; large
  title on root tabs, compact on pushed screens; Android back button handled.
- **Scrolling** — `ScrollView` (forms, details) or `FlashList` (lists) with visible scroll
  indicators, pull-to-refresh, and bottom padding = tab bar + safe area so nothing hides behind
  the floating bar.
- **Keyboard** — `react-native-keyboard-controller` (`KeyboardAwareScrollView` +
  `KeyboardStickyView` for sticky footers like "Save & issue"): the focused input always scrolls
  above the keyboard, Next/Done moves between fields, tapping outside dismisses. Never a fixed
  footer that the keyboard covers.
- **States** — loading skeleton, empty state with next action, error with retry, offline banner.

## 3. Fast and smooth

- Lists: `FlashList`, stable keys, memoised rows, paginated API (`?page=`), search debounced 300 ms.
- Data: TanStack Query cache, **optimistic updates** for payments/status changes, prefetch the
  detail screen on list press-in, refetch on focus.
- Startup: splash held only until token + `me` load; fonts preloaded; images as WebP.
- Animation: Reanimated on the UI thread only; respect "Reduce motion".
- Targets: cold start < 2 s on a mid Android; list scroll at 60 fps; every tap gives feedback
  < 100 ms (pressed state / haptic).

## 4. Auth = web

Email + password, Google, sign-up with email code verification, forgot password — exactly as
`07-WEB-FEATURE-INVENTORY.md`. No mobile-OTP login. Token in `expo-secure-store`; 401 → sign out.

## 5. Accessibility & India

44 pt touch targets, Dynamic Type up to 200 % without clipping, contrast 4.5:1, screen-reader
labels on icon buttons, ₹ with lakh/crore grouping, dates "13 Nov 2026", Hindi-ready strings.

## 6. Keeping up with the web

Every web change follows `05-WEB-MOBILE-SYNC.md` via the `mobile-sync` skill. A new web page
gets a row in `07-WEB-FEATURE-INVENTORY.md` and a screen (or "web-only" reason) before release.

## Header, status bar and motion (2026-11-13)

- `<Screen collapsible={…}>` holds header parts that slide away as the list scrolls (filter chips on
  Invoices/Quotations, search on Parties). `headerExtra` stays visible. Lists report scroll through
  `useScreenScroll()` — `PagedList` already does.
- `Screen` draws a fixed brand backdrop behind the status bar and re-applies the bar style on focus,
  so the light icons never sit on white content (Android edge-to-edge). `app.json → androidStatusBar`
  is the native fallback (needs a new build).
- Taps use `PressableScale` (spring, UI thread) — `Button` and `Row` already do. Shared values are
  written with `.set()` (React Compiler rule).
