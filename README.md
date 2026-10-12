# TravelEasy (remake)

A solo rebuild of TravelEasy, a travel-agency web app first built as a team school project. Customers search trips,
book them for one or more travellers and get an invoice; staff manage trips, offers and bookings. It is a portfolio
demo, built to be accessible (WCAG 2.2 AA), privacy-friendly (GDPR) and secure by default, and it is self-hosted
with Docker.

**Stack:** Laravel, Inertia, React, TypeScript, Tailwind CSS and PostgreSQL, all running in Docker.

## Getting started

You need Docker Desktop (WSL 2 backend on Windows) and Git; no local PHP, Composer, Node or PostgreSQL. Follow the
first-run steps in the [development environment guide](docs/deploy/dev.md). After that, `docker compose up -d`
starts the app on `http://localhost:8000`.

## Documentation

- [Development environment guide](docs/deploy/dev.md): first run, daily commands, rebuilds and safe resets
- [Testing guide](docs/testing.md): test layers, rules and quality commands
- [Roadmap and milestones](docs/plan.md)
- [Architecture decision records](docs/adr/)

## License

Released under the [MIT License](LICENSE).
