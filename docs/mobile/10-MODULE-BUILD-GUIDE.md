# Building a module in the mobile app

How every new area (items, purchases, expenses, GST, reports, settings…) is added to `mobile/`,
so they all look and behave the same. Read `08-APP-ENGINEERING-RULES.md` first.

## Files per module (no shared-file edits)

Each module owns its own files, so several modules can be built at once without clashing:

| What | Where |
|---|---|
| Types + API calls | `src/api/<module>.ts` — import `get/post/put/patch/del/qs` from `./client`; types live in the same file |
| Query/mutation hooks | `src/hooks/<module>.ts` — TanStack Query; query keys prefixed with the module name; mutations invalidate their keys **and** `['dashboard']` when money/counts change |
| Screens | `src/app/(app)/<module>/…` (routes only, thin) |
| Module-only components | `src/screens/<module>/…` |

Do **not** edit `src/api/endpoints.ts`, `src/api/types.ts`, `src/hooks/queries.ts`, the tab bar, the
More tab or `create-sheet.tsx` — the lead wires menus and the + sheet. You may *import* from them.

## Route plan (the + sheet and dashboard already link to these)

```
items/            index (list + search + low-stock filter) · new · [id] (edit + stock card) · [id]/stock (ledger + adjust)
vendors/          index · new · [id] (detail + bills) · [id]/edit
purchases/        index (status chips) · new · [id] · [id]/edit · [id]/payment
expenses/         index (category chips, month) · new · [id] (edit)
cash-memos/       index · new · [id] · [id]/edit
gst/              index (hub: GSTR-1, 3B, 2B, HSN for a month) · gstr1 · gstr3b · gstr2b · hsn
reports/          index (P&L, ageing, registers…) · pnl · aging · [report]
settings/         index (company profile) · invoice (numbering, terms, template) · bank · team · logo
quotations/       new · [id]/edit
parties/          new · [id]/edit
invoices/[id]/    edit · credit-note
search            notifications
```

Every list screen accepts the query params the dashboard sends (`?status=`, `?month=YYYY-MM`, `?from=&to=`).

## Screen rules

- Build every screen on `<Screen>` (`src/components/screen.tsx`):
  - lists: `scroll={false}` + `PagedList` (or FlashList wired to `useScreenScroll()`); search in `headerExtra`, chips/filters in `collapsible`.
  - forms: default scroll + `footer` with the Save button; `nav="close"`.
  - detail screens with a big amount: `hero`.
- **No create buttons in headers** — creating starts from the + sheet or an empty state's button. Header `right` holds only `BarAction` icons (share, PDF, more).
- Reuse: `TextField`, `PickerSheet`/`PickerRow`, `LineItemsEditor`, `PartyPicker`, `Chips`, `Segmented`, `Card`, `Row`, `KeyValue`, `EmptyState`, `Loading`, `ErrorState`, `StatusPill`/`Pill`, `Button`, `PressableScale`, charts in `charts.tsx`, `downloadAndShare` (PDF/CSV/XLSX), `money`/`moneyShort`/`date` in `utils/format.ts`.
- Amounts: `money()` in rows/detail; `moneyShort()` where space is tight (tiles, charts), with the full value in `accessibilityLabel`.
- Server-side errors: `ApiError.fieldErrors` under each field (see `invoices/new.tsx`).
- Business rules (GST split, numbering, stock, roles) stay on the server — the app only displays what the API returns.
- Role limits: hide actions the API would refuse (`me` → role) and show the 403 message if it still happens.
- Every list: loading, empty (with a create button that opens the create route), error with retry, pull-to-refresh.
- Run `npx tsc --noEmit` and `npx expo lint` (0 errors, 0 warnings) before handing back.
