# Apna Invoice — Mobile App Docs

Planning documents for the Apna Invoice mobile app (iOS + Android), built with
**React Native + Expo** on top of the **existing Laravel JSON API** (`routes/api.php`).

The old `mobile/` folder was removed on 2026-11-13 to start clean. Its code is still in
git history (last commit before removal: `9cca3f3`) if anything needs to be looked up.

## Order of work

1. **Documents** (this folder) — scope, tech, API mapping, screens. ← *we are here*
2. **Design** — screen designs from `04-SCREENS-AND-FLOWS.md`, using Claude Design /
   generated images. Nothing visual is decided in these docs on purpose.
3. **Decisions sign-off** — answer the open questions in `06-DECISIONS.md`.
4. **Coding** — scaffold a fresh `mobile/` and build phase by phase.

## Documents

| File | What it answers |
|---|---|
| [01-PRODUCT-BRIEF.md](01-PRODUCT-BRIEF.md) | Who the app is for, what it must do, phases |
| [02-TECH-STACK.md](02-TECH-STACK.md) | Libraries, project layout, conventions, build & release |
| [03-API-MAP.md](03-API-MAP.md) | Every app feature → the API endpoint it uses, and the gaps |
| [04-SCREENS-AND-FLOWS.md](04-SCREENS-AND-FLOWS.md) | Screen inventory and key flows (input for design) |
| [05-WEB-MOBILE-SYNC.md](05-WEB-MOBILE-SYNC.md) | How web changes flow into the app; the parity matrix |
| [06-DECISIONS.md](06-DECISIONS.md) | Open questions you need to decide before coding |
| [07-WEB-FEATURE-INVENTORY.md](07-WEB-FEATURE-INVENTORY.md) | What the web does today (QA'd with dummy data) and where each feature lives in the app |
| [08-APP-ENGINEERING-RULES.md](08-APP-ENGINEERING-RULES.md) | DRY, screen scaffold (safe area, top bar, scroll, keyboard), performance, auth |
| [design/README.md](design/README.md) | Brand tokens, chosen direction, screen list and the Claude Design canvas link |
| [09-RELEASE-AND-UPDATES.md](09-RELEASE-AND-UPDATES.md) | Dev builds, EAS profiles, OTA updates (with the in-app update modal), store compliance checklist |

## Keeping web and mobile in sync

The `mobile-sync` skill (`.claude/skills/mobile-sync/SKILL.md`) and the rule in the
root `CLAUDE.md` make sure that any change to a web feature, API endpoint, or business
rule is checked against the mobile app and recorded in `05-WEB-MOBILE-SYNC.md`.
