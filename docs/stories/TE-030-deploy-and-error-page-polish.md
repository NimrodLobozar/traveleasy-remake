---
id: TE-030
title: Deploy and error-page polish (chore)
status: in-test
priority: should
epic: M0 CI/CD to GHCR
agents: [devops-engineer, backend-developer]
testers: [qa-engineer, security-privacy-reviewer, accessibility-auditor]
design: not-needed
rework_rounds: 0
---

## User story
As the maintainer, I want the Low and Minor findings from the TE-019 and TE-025 reviews fixed, so that the secrets helper never leaves
secrets in a log driver, the authenticator shows the right issuer, and the static error page is accessible.

## Context
- Security review of TE-025 (Lows) and accessibility review of TE-019 (Minor), 2026-11-03.
- Related: TE-019 AC9 (static 502/504 page), TE-025 AC3 (helper), TE-026.

## Acceptance criteria
- [ ] AC1 (security Low): Given the documented `generate-secrets` command in `docs/deploy/casaos-portainer.md` (around line 39),
      `docs/deploy/production-image.md` and the `GenerateSecrets` docblock, when read, then each uses `docker run --rm -it
      --log-driver none ...`, so a non-default Docker log driver never keeps the printed secrets.
- [ ] AC2 (security Low): Given the helper runs under `docker run`, where `APP_NAME` is unset, when it prints the TOTP URI, then the
      issuer is "TravelEasy": either hard-coded in `SecretsGenerator::totpUri` or via `ENV APP_NAME=TravelEasy` in the production
      image; a Pest test asserts the issuer with `APP_NAME` unset.
- [ ] AC3 (a11y Minor, WCAG 1.3.1 / 2.4.6 / 3.1.2): Given the static 502/504 page in `docker/prod/Caddyfile`, when read, then it has
      exactly one `<h1>`, the English part is an `<h2 lang="en">` (or separate sections each carrying `lang`), and the mixed nl/en
      `<title>` is handled (for example a Dutch title with the English text in a `lang="en"` element, or a single-language title);
      the page still validates and is re-checked with axe.

## Quality criteria
- [ ] Tests: Pest test for AC2; existing compose, Caddyfile and docs-contract tests stay green; axe scan of the static page for AC3.
- [ ] Accessibility: WCAG 2.2 AA; heading structure and language of parts as in AC3.
- [ ] Security/privacy: reviewer confirms both Lows are closed.
- [ ] i18n: static page keeps nl + en copy.
- [ ] Performance: n/a.
- [ ] Docs updated: `docs/deploy/casaos-portainer.md`, `docs/deploy/production-image.md`.

## Out of scope
- Making FrankenPHP produce 502/504 (not possible with the classic `php_server`); NPM custom error page (TE-026 AC14).

## Open questions
- (none)

## Rework log
(empty)

## Changelog
(filled when done)
