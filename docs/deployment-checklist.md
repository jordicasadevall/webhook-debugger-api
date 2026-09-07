# Deployment Checklist

Project-specific steps for a public release, on top of the generic
Docker/server setup covered in [`production.md`](production.md).

## Infrastructure

- [ ] Pick and provision a host, Docker + Docker Compose available.
- [ ] Point a real domain at the server's IP (Caddy's auto-HTTPS needs a real
      domain, not a bare IP, and ports 80/443 reachable for the ACME challenge).
- [ ] Open required ports in the firewall/security group (80, 443, and
      443/udp for HTTP3).

## App secrets/config

Set via the real deploy environment — never commit these:

- [ ] `APP_ENV=prod`
- [ ] `APP_SECRET` — currently empty in `.env`; generate a real random value
- [ ] `DATABASE_URL` — real production Postgres connection string
- [ ] `GATEWAY_SECRET` (or the legacy `RAPIDAPI_PROXY_SECRET` alias) — only
      needed if you're putting this behind an API gateway; must match what's
      configured there (see
      [`GatewaySecretGuard`](../src/EventListener/GatewaySecretGuard.php)).
      Leave empty to serve the API directly with no gateway.
- [ ] `GATEWAY_SECRET_HEADER` — only needed if your gateway doesn't use
      RapidAPI's `X-RapidAPI-Proxy-Secret` header name.
- [ ] `EVENT_RETENTION_DAYS` — confirm `7` is the value you want in prod
- [ ] `SERVER_NAME` — real domain

## Database

- [ ] Run `bin/console doctrine:migrations:migrate --no-interaction` against
      the prod DB on first deploy.

## Scheduled jobs

- [ ] Wire up cron (or host equivalent) for `bin/console app:events:cleanup`
      — nothing runs it automatically, it needs an external scheduler.

## Optional: listing on RapidAPI

- [ ] Update `servers:` in [`openapi.yaml`](../openapi.yaml) to include the
      deployed URL.
- [ ] Import the spec via RapidAPI's "Add New API → Import from OpenAPI".
- [ ] Set the base URL in RapidAPI to the deployed host.
- [ ] Configure RapidAPI to send `X-RapidAPI-Proxy-Secret` matching
      `GATEWAY_SECRET`.
- [ ] Define a pricing/quota plan.
- [ ] Test each endpoint via RapidAPI's built-in tester before submitting
      for review.

## Known residual gaps

Not blocking, but worth knowing before real traffic:

- [ ] No structured observability beyond default 5xx logging.
- [ ] Rate limiter state is filesystem-backed per-container — fine for a
      single instance, resets on restart, won't be shared if this ever
      scales to multiple replicas.

## Final smoke test after deploy

- [ ] Create an inbox, send a test webhook, list/view/replay it, confirm
      rate limits and (if configured) the gateway secret check behave as
      expected against the real URL.
