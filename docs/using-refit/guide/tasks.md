---
title: Tasks
description: The structural and cleanup tasks refit offers once the icon set is chosen.
order: 3
---

# Tasks

After the icon question you pick from a list of structural tasks. Only tasks that
fit the kit refit detected are offered, so you are never asked to choose
something that would do nothing.

| Group | Task | Key |
|---|---|---|
| Structure | Use components instead of partials | `partials-to-components` |
| Structure | Group components into folders | `namespace-components` |
| Structure | Show toasts at the top of the screen | `toasts-at-top` |
| Cleanup | Delete the layouts the kit does not render | `single-layout` |
| Cleanup | Remove the Flux Pro `@source` line from `app.css` | `remove-flux-pro-source` |

The keys are what [`--answers`](/docs/using-refit/guide/the-refit-command#running-without-prompts)
takes.

## Use components instead of partials

Turns `resources/views/partials` into anonymous Blade components. The kit ships
two — `partials.head` and `partials.settings-heading` — pulled in with `@include`
42 times between them. Both are self-contained, so the move is mechanical:

```blade
@include('partials.head')   →   <x-head />
```

Once every partial has moved out, the directory goes too — unless something
unplanned is still sitting in it, which is reported and left alone.

> [!NOTE]
> An `@include` that passes data has no mechanical translation: the component
> would need matching props. Those are reported and left for you.

## Group components into folders

Sorts the kit's loose anonymous components by what they are for, and drops the
prefix once the folder carries it — `<x-app-logo />` becomes `<x-brand.logo />`.
With both structure tasks picked, the kit comes out like this:

```
resources/views/components/
├── auth/
│   ├── header.blade.php
│   ├── session-status.blade.php
│   ├── passkey-registration.blade.php
│   └── passkey-verify.blade.php
├── brand/
│   ├── logo.blade.php
│   └── logo-icon.blade.php
├── layout/
│   ├── head.blade.php
│   └── desktop-user-menu.blade.php
├── settings/
│   └── heading.blade.php
└── ui/
    └── placeholder-pattern.blade.php
```

Three layers decide where each file lands, most specific first:

1. **The curated map**, which carries the judgement calls for the components the
   kit actually ships — `passkey-verify` reads as part of the auth flow, so it
   goes under `auth/` rather than into a folder of its own.
2. **A shared prefix.** A leading token two or more components share becomes
   their folder, and is dropped from the filename now that the folder says it.
3. **The fallback folder**, `ui/`, for anything with nothing to group with.

Only top-level files move. Anything already in a subfolder is namespaced, and
Livewire's own `x-layouts::` and `x-pages::` views are a different mechanism
entirely.

To lay the tree out your own way, register the task with your own map from a
service provider:

```php
use Onelegstudios\Refit\Facades\Refit;
use Onelegstudios\Refit\Tasks\NamespaceComponents;

Refit::task(new NamespaceComponents(['app-logo' => 'marketing/logo'], fallback: 'shared'));
```

## Show toasts at the top of the screen

The kit renders a bare `<flux:toast.group>` in every layout, which leaves Flux on
its `bottom end` default — the corner a sidebar footer, a cookie bar or a mobile
keyboard tends to occupy. Moving the toasts up adds the position the group was
missing, and nothing else:

```blade
<flux:toast.group position="top end">
    <flux:toast />
</flux:toast.group>
```

A group that already carries a `position` is left as it is.

## Delete the layouts the kit does not render

Both layout families are built the same way: `layouts/auth.blade.php` and
`layouts/app.blade.php` each render exactly one of the variants sitting in the
folder beside them.

```blade
<x-layouts::app.sidebar :title="$title ?? null">
```

That leaves three auth layouts where one is used — card, simple and split — and
two application shells where one is used, sidebar and header. The unrendered app
shell is the expensive one: a whole navigation chrome with its own brand mark,
navigation and user menu, quietly drifting out of step with the shell you do use
every time you touch one of them.

Which variant survives is read out of each delegating layout rather than asked, so
the task cannot pick a different answer than your application already has. Swap
`layouts/app.blade.php` over to `<x-layouts::app.header>` before running the task
and refit follows you — it keeps whichever variant that file names.

Both families are one task because they are one decision, and every kit ships
both. The plan still lists each file it will delete, so a single answer hides
nothing from the preview. To limit it to one family, register the task yourself:

```php
use Onelegstudios\Refit\Facades\Refit;
use Onelegstudios\Refit\Tasks\KeepOneLayout;

Refit::task(new KeepOneLayout(['auth']));
```

## Remove the Flux Pro @source line from app.css

Every variant ships this on line 6 of `resources/css/app.css`:

```css
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
```

It points at a directory that only exists with a Flux Pro licence. The task is
offered only when Flux Pro is absent, so buying a licence later is never quietly
broken.

## Adding tasks of your own

The list is not fixed — a task is a class implementing a small interface, and
refit handles the ordering, the preview and the apply. See
[Writing your own task](/docs/using-refit/guide/custom-tasks).
