# Security Policy

## Supported versions

This project is deployed as a single rolling version from the `main` branch.
Security fixes are made against `main`; there are no maintained older release
branches.

## Reporting a vulnerability

Please **do not** open a public GitHub issue for a security vulnerability.

Instead, use
[GitHub Security Advisories](https://github.com/jordicasadevall/webhook-debugger-api/security/advisories/new)
to report it privately. Include:

- A description of the vulnerability and its potential impact.
- Steps to reproduce, or a proof of concept.
- Any affected version/commit information.

You should get an initial response within a few days. If a report is
confirmed, a fix will be prepared and a coordinated disclosure timeline
agreed with you before any public advisory is published.

## Scope

Areas of particular interest for security review, given what this project
does:

- [`src/Service/WebhookReplayer.php`](src/Service/WebhookReplayer.php) — SSRF
  protections on the replay endpoint (blocked IP ranges, DNS-rebinding
  guard).
- [`src/EventListener/GatewaySecretGuard.php`](src/EventListener/GatewaySecretGuard.php)
  — the optional gateway-secret check.
- Rate limiting on the webhook receiver and replay endpoints
  ([`config/packages/framework.yaml`](config/packages/framework.yaml)).
