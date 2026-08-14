---
title: Supported starter kits
description: The Livewire variations refit knows, and the one it does not.
order: 2
---

# Supported starter kits

Every variation `laravel new` can generate for the Livewire kit:

| Variation | What it is |
|---|---|
| `livewire` | Built-in auth, single-file components |
| `livewire-class-components` | Built-in auth, Livewire class components |
| `livewire-teams` | Built-in auth with teams |
| `livewire-workos` | WorkOS AuthKit |
| `livewire-workos-teams` | WorkOS AuthKit with teams |

They are one repository with five branches, picked from the answers you give
`laravel new` about authentication provider, teams support, and single-file
components.

The **blank** Livewire kit is not supported: it ships without Flux and without
auth, so there is nothing to refit.

## What varies between them

Refit does not branch on the variation name — it never sees one. It reads the
tree and adapts:

- **Component style** decides where the kit's Livewire views live:
  `resources/views/pages` for single-file components,
  `resources/views/livewire` for class components. Single-file components carry
  an installer-applied prefix on the filename, which is why refit globs for those
  views rather than testing an exact path.
- **Features** — teams, WorkOS, passkeys, two-factor, registration — are whatever
  survived `install:features`, and each is detected from a file that either
  exists or does not.
- **Flux edition** decides whether the `@source` cleanup job is offered at all,
  and which stubs are scanned for the icons Flux renders internally.

The upshot is that a variation not on this list still gets a sensible run, as
long as it is recognisably the Livewire kit: jobs that do not fit are not
offered, and nothing is assumed that disk did not answer.

## Fixtures

Refit's test suite runs against real copies of all five, downloaded with
`composer fixtures`. They are gitignored, and tests that need one skip themselves
when it is absent. See [Testing](/docs/development/contributing/testing).
