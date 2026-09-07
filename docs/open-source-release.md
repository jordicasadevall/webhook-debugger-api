# Open-sourcing webhook-debugger-api

## Context

This repo was built as a private, RapidAPI-monetized product on top of the
`dunglas/symfony-docker` skeleton. It is functionally complete (6 endpoints,
28 tests, OpenAPI spec, prod Docker target) but carries the marks of a private
project: the skeleton's `LICENSE` still names Fabien Potencier, `composer.json`
still identifies as `symfony/skeleton`, the README is a single heading, CI never
runs the test suite, the only auth mechanism is hardcoded to RapidAPI's header,
and the OpenAPI spec points at a personal deployment domain.

The goal is a repo a stranger can clone, run, understand, and contribute to —
with maximum adoption reach, per the license answer — while the existing
RapidAPI deployment keeps working unchanged.

**Security pre-check (done):** `git log --all -p` over `.env*` shows no real
secret was ever committed — only `!ChangeMe!` placeholders, an empty
`RAPIDAPI_PROXY_SECRET`, and a throwaway dev `APP_SECRET`. **History does not
need rewriting.** `notes`, `.ai/`, `.idea/`, `.claude/` are already untracked.

---

## 0. Plan file location

Copy this plan to `docs/open-source-release.md` so it lives with the project,
per the standing preference that plans live in the project folder.

## 1. Licensing and project identity

- **`LICENSE`** — keep MIT (best adoption reach; matches the Symfony ecosystem
  and the MIT-licensed skeleton/symfony-docker code already vendored here).
  Replace the copyright line with `Copyright (c) 2026 Jordi Casadevall`.
- **`NOTICE`** (new, short) — credit that the Docker/FrankenPHP setup and the
  `docs/*.md` operational guides derive from
  [`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker) (MIT),
  and that the app skeleton derives from `symfony/skeleton` (MIT).
- **`composer.json`** — change `name` to `jordicasadevall/webhook-debugger-api`,
  write a real `description`, keep `"license": "MIT"`, add an `authors` block,
  and drop `"type": "project"`'s skeleton description wording.

## 2. De-vendor the auth guard

Rename `src/EventListener/RapidApiProxyGuard.php` →
`src/EventListener/GatewaySecretGuard.php`:

- Constructor takes two autowired env params instead of one:
  `%env(GATEWAY_SECRET)%` and `%env(GATEWAY_SECRET_HEADER)%`.
- `GATEWAY_SECRET_HEADER` defaults to `X-RapidAPI-Proxy-Secret` in `.env`, so
  the current production deployment keeps working with no config change.
- Keep the existing behaviour exactly: empty secret ⇒ no-op; `EXEMPT_ROUTES`
  (`webhook_receive`, `docs_ui`, `docs_spec`) stay exempt; `hash_equals`
  comparison; the same 403 JSON body but with a vendor-neutral message
  (`"A valid gateway secret is required"`).
- **Back-compat**: `.env` keeps `RAPIDAPI_PROXY_SECRET` as a documented alias —
  define `GATEWAY_SECRET=${RAPIDAPI_PROXY_SECRET}` in `.env` so an existing
  deploy environment that only sets `RAPIDAPI_PROXY_SECRET` still works.
- Rename `tests/EventListener/RapidApiProxyGuardTest.php` →
  `GatewaySecretGuardTest.php`; update the class/constructor calls and add one
  test covering a custom header name.
- Update the reference in `docs/deployment-checklist.md`.

## 3. Decouple the OpenAPI spec from the personal deployment

`openapi.yaml`:

- Replace the `servers:` production entry
  (`https://webhook-debugger.jordicasadevall-engineering.top`) with the
  self-hosted-first ordering: `- url: /` (same server) first, then the hosted
  instance as a clearly-labelled optional second entry.
- Rewrite the `info.description` auth paragraph: describe the optional gateway
  secret as the self-hosted story, and mention RapidAPI only as one way to put
  a gateway in front.

## 4. README (the main deliverable)

Replace the one-line `README.md` with, in order:

- Logo (`webhook-debugger-logo.png`, already in the repo) + one-sentence pitch:
  receive, inspect, and replay webhooks.
- Badges: CI status, license, PHP version.
- **Why** — debugging webhook integrations without a public endpoint.
- **Quick start** — `docker compose up --wait`, then
  `curl -k -X POST https://localhost/inboxes`, point a provider at
  `/in/{inboxId}`, list/inspect/replay. Real copy-pasteable curl for all six
  endpoints (derived from `openapi.yaml`, not invented).
- **API docs** — Swagger UI at `/docs`, spec at `/docs/openapi.yaml`
  (`src/Controller/DocsController.php`).
- **Configuration table** — every env var in `.env` with default and meaning:
  `GATEWAY_SECRET`, `GATEWAY_SECRET_HEADER`, `EVENT_RETENTION_DAYS`,
  `DATABASE_URL`, `APP_SECRET`, `SERVER_NAME`, ports.
- **Security model** — SSRF protections in `src/Service/WebhookReplayer.php`
  (private-range blocklist + DNS-rebinding guard), rate limits on the receiver
  and replay endpoints, request body cap, optional gateway secret.
- **Deployment** — link `docs/production.md` and `docs/deployment-checklist.md`;
  note the cron requirement for `bin/console app:events:cleanup`.
- **Development** — running tests, migrations, Xdebug (link `docs/xdebug.md`).
- **Credits/license** — points at `NOTICE`.

## 5. Community scaffolding (standard set)

- **`CONTRIBUTING.md`** — dev container / `docker compose up`, how to run
  `bin/phpunit`, run migrations, coding style (`.editorconfig` + Symfony
  conventions), commit message style matching the existing imperative-mood
  history, PR expectations (tests for behaviour changes).
- **`SECURITY.md`** — supported versions, private reporting via GitHub Security
  Advisories, expected response time. Do **not** print a personal email.
- **`CODE_OF_CONDUCT.md`** — Contributor Covenant 2.1, with the report contact
  pointing at GitHub private reporting rather than a personal address.
- **`.github/ISSUE_TEMPLATE/bug_report.yml`**, `feature_request.yml`,
  **`config.yml`**, and **`.github/PULL_REQUEST_TEMPLATE.md`**.

## 6. Make CI actually verify the project

`.github/workflows/ci.yaml` currently builds images, curls the homepage, and
leaves every meaningful step commented out. Uncomment and fix:

- Create the test database and run migrations
  (`bin/console -e test doctrine:database:create`,
  `doctrine:migrations:migrate --no-interaction`).
- Run `bin/phpunit` (28 tests exist and must pass).
- Run `doctrine:schema:validate`.
- Drop the Mercure reachability check — Mercure is unused by this app.
- Fix the HTTP reachability check: `curl --fail-with-body http://localhost`
  hits a route this app does not define (no `/` route), so it will fail once
  it is trusted. Point it at `/docs` instead.

## 7. Housekeeping

- `.gitignore` — add `/.claude/` and `/.playwright-mcp/` alongside the existing
  `/notes`, `/.ai`, `/.idea` entries.
- `.mcp.json` / `mcp.json` — two overlapping, machine-specific MCP configs (one
  hardcodes the container name `webhook-debugger-api-php-1`). Remove `mcp.json`
  (unreferenced duplicate) and keep `.mcp.json`, or untrack both. Recommend:
  keep `.mcp.json`, delete `mcp.json`.
- `docs/deployment-checklist.md` — strip the resolved-issue narrative
  (the struck-through `frankenphp_prod` section, the `example.com` item that is
  no longer true) so it reads as a checklist, not a work log.
- `.env.dev` — regenerate the committed dev `APP_SECRET`. It is dev-only and
  harmless, but a fresh value avoids every clone sharing one.

---

## Files touched

| Action | Path |
|---|---|
| Rewrite | `README.md`, `LICENSE`, `openapi.yaml` (servers + description), `.github/workflows/ci.yaml` |
| New | `NOTICE`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `.github/ISSUE_TEMPLATE/*`, `.github/PULL_REQUEST_TEMPLATE.md`, `docs/open-source-release.md` |
| Rename + edit | `src/EventListener/RapidApiProxyGuard.php` → `GatewaySecretGuard.php`, `tests/EventListener/RapidApiProxyGuardTest.php` → `GatewaySecretGuardTest.php` |
| Edit | `composer.json`, `.env`, `.env.dev`, `.gitignore`, `docs/deployment-checklist.md` |
| Delete | `mcp.json` |

## Verification

1. `docker compose up --wait --build` — stack comes up.
2. `docker compose exec php bin/console doctrine:migrations:migrate -n` then
   `docker compose exec php bin/phpunit` — all tests green, including the
   renamed `GatewaySecretGuardTest`.
3. Gateway guard, both directions:
   - Default (empty secret): `curl -k -X POST https://localhost/inboxes` → 201.
   - With `GATEWAY_SECRET=test123` in `.env.local`, restart, repeat → 403; the
     same call with `-H 'X-RapidAPI-Proxy-Secret: test123'` → 201.
   - With `GATEWAY_SECRET_HEADER=X-Api-Gateway-Secret`, the new header works and
     the old one is rejected.
4. End-to-end: create inbox → `POST /in/{inboxId}` → `GET /inboxes/{id}/events`
   → `GET .../events/{eventId}` → replay → delete. Confirms nothing in the
   rename broke routing.
5. Open `https://localhost/docs` — Swagger UI loads and the `servers` dropdown
   defaults to the same-origin entry.
6. Follow the README quick start verbatim in a fresh clone; every command must
   work as written.
7. `git ls-files | xargs grep -rIn 'jordi\|casadevall\|wavecontrol'` — only the
   intentional authorship lines in `LICENSE`/`composer.json` remain.
8. Push a branch and confirm the CI workflow goes green with the newly enabled
   test steps.
