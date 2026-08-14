---
title: The refit command
description: The five stages of a run, the options, and the checks that stop one.
order: 1
---

# The refit command

```bash
php artisan refit
```

The command runs in five stages — **detect, ask, plan, confirm, apply**. Nothing
touches disk until you have seen the plan and agreed to it.

| Option | What it does |
|---|---|
| `--dry-run` | Print the plan and exit without changing anything |
| `--force` | Run even though the git working tree is dirty, or there is no repository |
| `--answers=` | JSON answers, for a run with no prompts |

## Detect

Refit probes the filesystem rather than asking you what you installed. Every
signal is a file that either survived `install:features` or did not:

| Signal | Read from |
|---|---|
| Component style | `app/Livewire/Settings` — only the class-component variant ships it |
| Teams | `app/Models/Team.php` |
| WorkOS AuthKit | `laravel/workos` in `composer.json` |
| Passkeys | `resources/js/passkeys.js` |
| Two-factor, registration | The kit's auth views, under either component style |
| Flux Pro | `livewire/flux-pro` in `composer.json` or `composer.lock` |

What it found is printed as the summary line at the top of the run, and it
decides which jobs you are offered.

## Preflight

Two conditions stop a run before anything is asked.

**`chisel.php` is still present.**

> This starter kit still has chisel.php — run `php artisan install:features`
> first, so refit sees the file tree you are actually keeping.

Not overridable, and deliberately so: refitting a tree that is about to be
chiselled means rewriting views you are about to delete.

**The working tree is not clean, or is not a repository at all.**

> Your git working tree has uncommitted changes. Commit or stash them first so
> `git checkout .` can undo this run, or pass `--force`.

Refit rewrites files in place and keeps no backup of its own, so git *is* the
undo. `--force` skips this check and both of its halves.

## Ask

Two questions, in order:

1. **Icons.** Keep the mix, go all-Heroicons, or go all-Lucide. See
   [Icons](/docs/using-refit/guide/icons).
2. **Jobs.** A multiselect of the structural and cleanup jobs that fit the
   detected kit. Only applicable jobs are listed, so you are never asked to pick
   something that would do nothing. See [Jobs](/docs/using-refit/guide/jobs).

## Plan

Your answers become a list of actions, sorted into stages that run in order:

| Stage | What lands here |
|---|---|
| `Dependencies` | Composer and npm changes, before anything reads `vendor/` |
| `Write` | Creating or overwriting files |
| `Move` | Moving, renaming and deleting files |
| `Reconcile` | Whole-tree reference rewriting, once every file has stopped moving |
| `Format` | Formatting and asset builds |
| `Finish` | Anything that must happen after the project is otherwise final |

The stages are what keep two jobs from tripping over one another: files stop
moving before the reconcile pass rewrites the references to them. When the plan
is not empty, refit appends `./vendor/bin/pint --dirty` to the `Format` stage so
the files it touched come out formatted.

A plan with nothing in it ends the run there:

> Nothing to do — the choices you made leave the project as it is.

## Confirm

The plan is printed in full, one line per action, grouped by stage. Answering no
leaves the project untouched; `--dry-run` prints the same list and exits before
the question.

## Apply

Actions run in stage order, each one echoed as it goes. Afterwards refit prints
any warnings, writes them to `REFIT-NOTES.md` if there are any
([configurable](/docs/using-refit/reference/configuration)), and offers to remove itself.

## Running without prompts

`--answers` takes the same shape the prompts produce, which is also how the test
suite drives the command:

```bash
php artisan refit --answers='{"icons":"lucide","tasks":["partials-to-components","namespace-components"]}'
```

| Key | Value |
|---|---|
| `icons` | `keep`, `heroicons` or `lucide`. Anything unrecognised falls back to `keep` |
| `tasks` | A list of task keys. Unknown keys are ignored — see [Jobs](/docs/using-refit/guide/jobs) for the list |

With `--answers` there is no confirmation step (passing answers *is* the
confirmation) and no offer to remove refit at the end. Combine it with
`--dry-run` to render a plan non-interactively.
