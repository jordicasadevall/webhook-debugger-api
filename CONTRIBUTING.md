# Contributing

Thanks for considering a contribution.

## Development setup

This project ships as a [Dev Container](https://containers.dev/) and a plain
Docker Compose setup — either works.

```console
git clone https://github.com/jordicasadevall/webhook-debugger-api.git
cd webhook-debugger-api
docker compose up --wait
```

Run the test suite before and after your change:

```console
docker compose exec php bin/phpunit
```

If your change touches the schema, add a migration and run it:

```console
docker compose exec php bin/console make:migration
docker compose exec php bin/console doctrine:migrations:migrate
```

## Coding style

- Follow `.editorconfig` (4-space indent, LF line endings, trailing newline).
- Match the existing style in the file you're editing — this codebase follows
  standard Symfony conventions (constructor property promotion, attribute-based
  routing/config, PHPUnit `#[Test]` attributes rather than `test`-prefixed
  method names).
- Keep changes focused. A bug fix doesn't need an accompanying refactor.

## Tests

Every behavior change needs a test. The suite is organized by the code it
covers:

- `tests/Controller/` — functional, HTTP-level tests per controller.
- `tests/Service/`, `tests/EventListener/`, `tests/Command/` — unit tests.

Run a single file with `docker compose exec php bin/phpunit tests/path/to/FileTest.php`.

## Commit messages

Imperative mood, one line summarizing the change and, where useful, why:
`Add DELETE /inboxes/{inboxId}/events/{eventId}`, `Fix DNS-rebinding SSRF
bypass in webhook replay`.

## Pull requests

- Describe what changed and why.
- Make sure `docker compose exec php bin/phpunit` and the linter (see
  [`.github/workflows/ci.yaml`](.github/workflows/ci.yaml)) pass — CI runs
  both on every PR.
- Update [`openapi.yaml`](openapi.yaml) and the README if you change or add
  an endpoint.

## Reporting security issues

Do not open a public issue for a security vulnerability — see
[`SECURITY.md`](SECURITY.md).
