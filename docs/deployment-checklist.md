# Deployment Checklist

Project-specific steps for the first public release, on top of the generic
Docker/server setup covered in [`production.md`](production.md).

## Infrastructure

- [ ] Pick and provision a host, Docker + Docker Compose available.
- [ ] Point a real domain at the server's IP (Caddy's auto-HTTPS needs a real
      domain, not a bare IP, and ports 80/443 reachable for the ACME challenge).
- [ ] Open required ports in the firewall/security group (80, 443, and
      443/udp for HTTP3).

## Prod image build

~~Building the `frankenphp_prod` target failed~~ — fixed. Root cause wasn't
missing build-time secrets, it was `symfony/uid` (used directly by
`Symfony\Component\Uid\Uuid` in the entities) only being present transitively
via `symfony/ai-mate`, a `require-dev`-only tool. Stripped out by `--no-dev`,
which broke every entity with an ID. Added `symfony/uid` as a direct
dependency; verified by building the real `frankenphp_prod` image, running
it against the dev Postgres, and confirming create-inbox → receive →
list-events works end to end.

## App secrets/config

Set via the real deploy environment — never commit these:

- [ ] `APP_ENV=prod`
- [ ] `APP_SECRET` — currently empty in `.env`; generate a real random value
- [ ] `DATABASE_URL` — real production Postgres connection string
- [ ] `RAPIDAPI_PROXY_SECRET` — real value, must match what's configured on
      the RapidAPI side (see [`RapidApiProxyGuard`](../src/EventListener/RapidApiProxyGuard.php))
- [ ] `EVENT_RETENTION_DAYS` — confirm `7` is the value you want in prod
- [ ] `SERVER_NAME` — real domain

## Database

- [ ] Run `bin/console doctrine:migrations:migrate --no-interaction` against
      the prod DB on first deploy.

## Scheduled jobs

- [ ] Wire up cron (or host equivalent) for `bin/console app:events:cleanup`
      — nothing runs it automatically, it needs an external scheduler.

## RapidAPI setup

- [ ] Update `servers:` in [`openapi.yaml`](../openapi.yaml) — still has the
      `example.com` placeholder, needs the real deployed URL.
- [ ] Import the spec via RapidAPI's "Add New API → Import from OpenAPI".
- [ ] Set the base URL in RapidAPI to the deployed host.
- [ ] Configure RapidAPI to send `X-RapidAPI-Proxy-Secret` matching
      `RAPIDAPI_PROXY_SECRET`.
- [ ] Define a pricing/quota plan.
- [ ] Test each endpoint via RapidAPI's built-in tester before submitting
      for review.

## Known residual gaps

Not blocking, but worth knowing before real traffic:

- [ ] OpenAPI doesn't document that the receiver accepts PUT/PATCH/DELETE
      too, only GET/POST.
- [ ] Malformed non-object JSON bodies (e.g. `"5"`) produce a PHP warning
      instead of a clean 400.
- [ ] Vestigial `identity_generation_preferences` line in
      `config/packages/doctrine.yaml`.
- [ ] No structured observability beyond default 5xx logging.
- [ ] Rate limiter state is filesystem-backed per-container — fine for a
      single instance, resets on restart, won't be shared if this ever
      scales to multiple replicas.

## Final smoke test after deploy

- [ ] Create an inbox, send a test webhook, list/view/replay it, confirm
      rate limits and the RapidAPI proxy check behave as expected against
      the real URL.
