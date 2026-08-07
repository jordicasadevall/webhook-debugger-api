# Deployment Checklist

Project-specific steps for the first public release, on top of the generic
Docker/server setup covered in [`production.md`](production.md).

## Infrastructure

- [ ] Pick and provision a host, Docker + Docker Compose available.
- [ ] Point a real domain at the server's IP (Caddy's auto-HTTPS needs a real
      domain, not a bare IP, and ports 80/443 reachable for the ACME challenge).
- [ ] Open required ports in the firewall/security group (80, 443, and
      443/udp for HTTP3).

## Fix the prod image build first

Building the `frankenphp_prod` target runs `composer run-script
post-install-cmd` (which includes `cache:clear`) at build time. That step
needs a real `DATABASE_URL`/`APP_SECRET` to succeed — confirmed by trying the
build locally without them. Resolve this (build-time secrets, or adjusting
the Dockerfile's install step) before the image can be built at all.

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
