# Docs tooling reuse — should any of this be a shared package?

The question: refit's docs pipeline will be rebuilt for every future package.
Should the reusable parts become a package installed in the package repo and on
the site?

Companion to [go-live.md](go-live.md) (the refit-side work) and
[docs-site.md](docs-site.md) (the site-side work). This file decides nothing —
it records the reasoning so the decision can be made later, once the pipeline
has actually run end to end.

Written 2026-08-14, after reading `publish-docs.yml` and both plans. Nothing
here is blocking: the recommendation is to ship refit first and extract after.

---

## Answer in one line

No single package. The reusable parts are **three artifacts with three
different delivery vehicles**, and only one of them is a Composer package.

## Why not one package

The publisher and the host share no code. They share a *data format* — a
directory tree with `_version.json` in it — which is a contract, not a
dependency.

|              | Publisher (package side)      | Host (site side)              |
| ------------ | ----------------------------- | ----------------------------- |
| Artifact     | GitHub Actions YAML           | PHP middleware + route loop   |
| Runs         | In CI, on release             | In-request, per page view     |
| Depends on   | `rsync`, `gh`                 | Laradocs config internals     |
| Consumers    | Every package repo, forever   | One site                      |

Two unrelated codebases under one `composer.json`. And Composer cannot deliver
the half that matters anyway: **a package cannot install a file into
`.github/workflows/`.**

`docs/` is also `export-ignore`d, so a site that `composer require`s a package
receives no documentation at all. That is already settled in
[go-live.md](go-live.md) under "Decisions already made" — docs are delivered by
push, not by require — and it independently rules out the "install it on the
site" half of the idea.

---

## 1. The publisher → a reusable workflow, not a package

`publish-docs.yml` is 142 lines of working logic: tag resolution, major-line
mapping, pre-release refusal, `--delete` sync, `_version.json` labelling,
graceful skip on forks.

It is **almost entirely package-agnostic**. Reading it for refit-specific
coupling turns up exactly two things:

- the default `docs-sources/refit` in `vars.DOCS_SITE_PATH`
- the string `refit` in the commit message

Everything else — the version regex, the major-directory mapping, the
concurrency group, the secrets-in-a-step-output skip pattern — applies unchanged
to any package. This is the highest-value extraction available.

Shape: move it to `onelegstudios/.github` as a `workflow_call` workflow, taking
`project` as an input and deriving the site path and commit message from it.
Each package repo then carries a stub:

```yaml
name: Publish docs

on:
  release:
    types: [released]
  workflow_dispatch:

jobs:
  publish:
    uses: onelegstudios/.github/.github/workflows/publish-docs.yml@v1
    with:
      project: refit
    secrets: inherit
```

- [ ] **Do this only after [go-live.md](go-live.md) step 5 passes.** The
      workflow has never executed. Extracting an unproven workflow means
      debugging the logic and the indirection at the same time.
- [ ] Pin the stub to a tag (`@v1`), not `@main` — a broken shared workflow
      would otherwise break every package's release at once
- [ ] Keep the skip-if-unconfigured behaviour; it is what stops forks going red

## 2. The site host → leave it in the site

[docs-site.md](docs-site.md) §1–2 (the route loop and `SetDocsProject`) is real,
reusable PHP. It should still stay in the website repo.

- One consumer, and there will only ever be one site.
- It is the piece [docs-site.md:96](docs-site.md) already flags as *"the one
  piece coupled to package internals"* — the thing that breaks on a Laradocs
  upgrade. In the site, that upgrade is one PR in one repo. As a package, it
  becomes a version matrix against Laradocs for no benefit.
- Adding a second package to the site is already a one-line change to the
  project array. There is no repetition to remove.

Revisit only if a second site ever needs to host the same docs.

## 3. The one genuine Composer package — `onelegstudios/docs-kit` (dev-only)

Not the publisher, not the host: the **local preview and the linting**.

[go-live.md](go-live.md) §3 describes the workbench rehearsal — mirror the
site's route prefix, enable versioning, stage `docs/` into a `v0/`
subdirectory so the preview matches production. Every future package needs
this, identically, or its docs preview lies about the shape the site will
serve.

Candidate contents:

- the workbench config override (path, `route.prefix`, `versions.enabled`)
- the `v0/` staging step (symlink or rsync into `build/`), so there is still
  one copy of the markdown to edit
- **the link checker** — [go-live.md](go-live.md) §2 describes it as "the loop
  used when the docs were written", i.e. a throwaway that should be a committed
  command
- a front-matter linter: required keys (`title`, `description`, `order`),
  duplicate `order` within a folder, pages orphaned from any `_index.md`

Dev-only, installed in each package repo, never on the site. This is the piece
that would have caught the link rot described below.

- [ ] Defer until the link-style decision is made — the checker's rules depend
      on which link style wins
- [ ] Build it in refit first as plain `bin/` scripts; extract at package two,
      once the rules have stopped moving

---

## Correction to fold into go-live.md

[go-live.md](go-live.md) §2 says *"53 of them across the tree, pointing at 19
distinct targets"* and the checklist says "Rewrite the 53 links".

Measured on 2026-08-14:

```
total:    57
distinct: 22
files:    17   (every page in docs/)
```

- [ ] Update the figures in [go-live.md](go-live.md) §2 before working that
      checklist item, so it is not marked done at 53

## Recommendation on the link style, since it gates everything

[go-live.md](go-live.md) §1 is still an unfilled blank and it blocks §2. Of the
three options in §2, **relative links** are the pick, for a reason the plan does
not state:

They are the only option that survives *both* a prefix change and the version
segment. That matters specifically because of the future packages this file is
about — a later package mounting at a different depth inherits docs conventions
that still resolve. Option 2 hardcodes the prefix and needs rewriting again at
v1; option 3 puts the base path in the *site's* provider, a coupling pointing
the wrong way for a package repo.

The per-page depth cost is real, but it is a one-time cost that the linter in §3
above can then guard permanently.

[go-live.md](go-live.md) §2 is also right that this must be verified under a
versioned layout rather than the flat workbench — relative resolution across the
`{version}` segment is reasoned, not yet observed.

---

## Suggested order

1. Ship refit: fill in the URL, fix the 57 links, rehearse, tag `v0.1.0`
2. Run `publish-docs.yml` by hand, prove it end to end
3. Build the site from [docs-site.md](docs-site.md)
4. **Then** extract the workflow (§1) — the cheap, high-value one
5. At package two, extract `docs-kit` (§3) once the rules have settled
6. Never extract the host (§2)
