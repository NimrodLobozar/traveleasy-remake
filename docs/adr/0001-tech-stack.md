# ADR-0001: Tech stack

- **Status:** Accepted
- **Date:** 2026-10-11
- **Decided by:** owner
- **Amended 2026-10-11:** the app sends no e-mail at all (`docs/adr/0005-no-outgoing-email.md`). Mail is no longer a
  requirement, Laravel's mail component isn't used, and Mailpit (listed in `docs/stack-options.md` "Supporting
  choices") is dropped. The rest of this decision is unchanged.

## Context
The remake needs built-in authentication with 2FA, per-record authorization, mail, queues, scheduled jobs and
encryption. It also needs complex accessible widgets (WCAG 2.2 AA, nl/en), Docker hot-reload development on Windows,
and a light production footprint on an amd64 office PC running CasaOS/Portainer. One developer maintains it, and
that developer already knows Laravel. Five options were compared in `docs/stack-options.md`.

## Decision
**Laravel + Inertia + React + TypeScript + PostgreSQL** (option 1 in `docs/stack-options.md`).
The supporting choices are listed in the "Supporting choices" table of that document and summarised in `CLAUDE.md` § Tech stack.

## Consequences
- One application and one image (plus worker/scheduler services from the same image); no separate API to secure.
- Auth, policies, mail, queues and the scheduler come from Laravel instead of being assembled by hand.
- Two languages (PHP + TypeScript); types are generated across the boundary so they can't drift.
- Public-page SEO needs Inertia SSR if it becomes important (an extra Node process, optional).
- Real-time messaging starts with polling; Laravel Reverb can be added later.
- Still to decide in later ADRs: production web server (FrankenPHP or Nginx + PHP-FPM), PDF library.
