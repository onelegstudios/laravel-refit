---
title: Development
description: Working on refit itself — architecture, the icon pipeline, tests and releases.
group: Contributing
order: 4
---

# Development

Documentation for people working *on* refit rather than with it.

| Page | What it covers |
|---|---|
| [Setup](/docs/development/setup) | Clone, install, and the commands you will actually use |
| [Architecture](/docs/development/architecture) | Detect, ask, plan, confirm, apply — and why the plan is a value |
| [Blade rewriting](/docs/development/blade-rewriting) | The tag parser, the rewriter, and the balance guard |
| [The icon pipeline](/docs/development/icon-pipeline) | The map, the bundled artwork, and the Flux internals manifest |
| [Testing](/docs/development/testing) | Pest, Testbench, fixtures, and what skips without a licence |
| [Releasing](/docs/development/releasing) | CI, the changelog, and how a version goes out |

## Ground rules

- Use Laravel-native package APIs and the existing service provider shape before
  adding abstractions.
- Add only the files and dependencies needed for the behaviour being
  implemented.
- Keep tests on observable behaviour through public APIs — commands, the
  registry, published resources, and the plans a project produces.
- Package names, namespaces, Composer metadata, publish tags and examples all say
  `onelegstudios/laravel-refit`.
