<div align="center">
    <h1>Refit</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/onelegstudios/laravel-refit"><img src="https://img.shields.io/packagist/v/onelegstudios/laravel-refit.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/onelegstudios/laravel-refit"><img src="https://img.shields.io/packagist/php-v/onelegstudios/laravel-refit.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/onelegstudios/laravel-refit"><img src="https://badge.laravel.cloud/badge/onelegstudios/laravel-refit?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/onelegstudios/laravel-refit/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/onelegstudios/laravel-refit/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/onelegstudios/laravel-refit"><img src="https://img.shields.io/packagist/dt/onelegstudios/laravel-refit.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Re-tailor a freshly installed Laravel Livewire starter kit around your own
conventions. One interactive command picks an icon set and a list of structural
jobs, shows you exactly what it intends to change, and applies it.

Refit is one-time scaffolding. It rewrites files in place, the changes are
one-way, and it offers to remove itself when it is done.

## Installation

Install it as a dev dependency, right after `laravel new`:

```bash
composer require --dev onelegstudios/laravel-refit
```

## Usage

```bash
php artisan refit
```

The command runs in five stages — detect, ask, plan, confirm, apply. Nothing
touches disk until you have seen the plan and agreed to it.

| Option | What it does |
| --- | --- |
| `--dry-run` | Print the plan and exit without changing anything |
| `--force` | Run even though the git working tree is dirty |
| `--answers=` | JSON answers, for a run with no prompts |

### Before it will run

Refit refuses to start unless two things are true:

- **`install:features` has already run.** While `chisel.php` is still present the
  file tree is not the one you are keeping, so refit would be rewriting views you
  are about to delete.
- **The git working tree is clean.** The changes are one-way, and a clean tree
  means `git checkout .` is the undo. That is why refit ships no backup or
  rollback of its own. Use `--force` to override.

### Icons

A fresh Livewire kit already speaks two icon sets: Flux resolves
[Heroicons](https://heroicons.com) by name, and the kit vendors four
[Lucide](https://lucide.dev) icons in as `resources/views/flux/icon/*.blade.php`
overrides for names Heroicons does not have. Refit offers three answers:

| Choice | What happens |
| --- | --- |
| **Keep the mix** | Nothing changes — what a fresh starter kit gives you |
| **Heroicons only** | Deletes the vendored Lucide overrides and points their usages at the Heroicons equivalents Flux already ships |
| **Lucide only** | Generates a Flux override for every icon in use, translating names as it goes |

Going all-Lucide also covers the icons Flux renders from *inside* its own
components — the chevron on a `flux:select`, the eye on a `viewable` input. Refit
cannot rewrite vendor code, so it writes those overrides at the Heroicons name
instead, and says so in the file. Without that, half your icons would follow the
switch and half would not.

`flux:icon.loading` gets the same treatment for the same reason: it is Flux's own
spinner rather than a Heroicon, so the override is written at `loading` and your
markup is left alone. It draws Lucide's `loader-circle`, with `animate-spin` so it
still spins.

Icon names are read in all three forms the kit writes them:

```blade
<flux:icon.key />
<flux:button icon="plus" icon:trailing="chevron-down" />
<flux:icon name="users" />
```

An icon refit has no translation for is reported with the file it appears in,
never silently dropped or guessed at.

Which icons Flux renders internally is read from the Flux package installed
alongside your project, so it stays right as Flux changes — and it sees the
`livewire/flux-pro` stubs when you have a licence. When there is nothing to scan,
refit falls back to the list recorded in
[`resources/flux/internal-icons.json`](resources/flux/internal-icons.json).

### Jobs

After the icon question you pick from a list of structural jobs. Only jobs that
fit the kit refit detected are offered, so you are never asked to choose
something that would do nothing.

| Group | Job |
| --- | --- |
| Structure | Use components instead of partials |
| Structure | Group components into folders |
| Structure | Show toasts at the top of the screen |
| Cleanup | Delete the unused auth layouts |
| Cleanup | Remove the Flux Pro `@source` line from `app.css` |

Grouping sorts the kit's loose anonymous components by what they are for, and
drops the prefix once the folder carries it — `<x-app-logo />` becomes
`<x-brand.logo />`. With both structure jobs picked, the kit comes out like this:

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

A component the map has no opinion about is grouped by a prefix it shares with
another, and one with nothing to group with lands in `ui/`. To lay the tree out
your own way, register the job with your own map from a service provider:

```php
use Onelegstudios\Refit\Facades\Refit;
use Onelegstudios\Refit\Tasks\NamespaceComponents;

Refit::task(new NamespaceComponents(['app-logo' => 'marketing/logo'], fallback: 'shared'));
```

The kit renders a bare `<flux:toast.group>` in every layout, which leaves Flux on
its `bottom end` default. Moving the toasts up adds the position the group was
missing, and nothing else:

```blade
<flux:toast.group position="top end">
    <flux:toast />
</flux:toast.group>
```

A group that already carries a `position` is left as it is.

### Running without prompts

`--answers` takes the same shape the prompts produce, which is also how the test
suite drives the command:

```bash
php artisan refit --answers='{"icons":"lucide","tasks":["partials-to-components","namespace-components"]}'
```

## Adding your own jobs

A job is a class implementing `Onelegstudios\Refit\Contracts\Task`. It says when
it applies and contributes actions to the plan; refit handles ordering, the
preview, and the apply.

```php
use Onelegstudios\Refit\Contracts\Task;
use Onelegstudios\Refit\Plan\{Plan, Report, Stage};
use Onelegstudios\Refit\Plan\Actions\DeleteFile;
use Onelegstudios\Refit\Project\{Feature, Project};
use Onelegstudios\Refit\Tasks\TaskGroup;

final class DropTheWelcomePage implements Task
{
    public function key(): string { return 'drop-welcome'; }
    public function group(): TaskGroup { return TaskGroup::Cleanup; }
    public function label(): string { return 'Delete the welcome page'; }
    public function hint(): string { return 'You are going to replace it anyway'; }

    public function appliesTo(Project $project): bool
    {
        return $project->exists('resources/views/welcome.blade.php');
    }

    public function contribute(Plan $plan, Project $project, Report $report): void
    {
        $plan->add(Stage::Move, new DeleteFile('resources/views/welcome.blade.php'));
    }
}
```

Register it in `config/refit.php`, or from a service provider:

```php
use Onelegstudios\Refit\Facades\Refit;

Refit::task(new DropTheWelcomePage);
```

Actions land in stages so that contributors never have to know about each other:
`Dependencies`, `Write`, `Move`, `Reconcile`, `Format`, `Finish`. Files stop
moving before the reconcile pass rewrites references, which is what keeps two
jobs from tripping over one another.

### Publishing the configuration

```bash
php artisan vendor:publish --tag="refit-config"
```

Say yes when refit offers to remove itself and it deletes the published config
on its way out — it only ever configured refit.

## Supported starter kits

Every variation `laravel new` can generate for the Livewire kit:

- `livewire` — built-in auth, single-file components
- `livewire-class-components` — built-in auth, Livewire class components
- `livewire-teams` — built-in auth with teams
- `livewire-workos` — WorkOS AuthKit
- `livewire-workos-teams` — WorkOS AuthKit with teams

The blank Livewire kit is not supported: it ships without Flux and without auth,
so there is nothing to refit.

## Testing

```bash
composer test
```

Tests that exercise a real starter kit skip themselves unless the fixtures have
been downloaded:

```bash
composer fixtures
```

### Contributing without a Flux Pro licence

You do not need one. Refit has no dependency on Flux — free or Pro — and the full
suite passes without either installed. Anything that needs the licensed
`livewire/flux-pro` stubs skips itself the same way the fixture tests do.

The one thing a licence unlocks is refreshing the record of which icons Flux
renders internally. That reads a sidecar install, set up once:

```bash
composer install --working-dir=.flux-pro
```

`.flux-pro/composer.json` is committed; what it installs is gitignored. It is a
separate manifest that the root `composer install` never reads, and that
separation is deliberate: Composer fetches every configured repository's
`packages.json` before resolving anything, so naming `composer.fluxui.dev` in
refit's own `composer.json` would fail the whole install for anyone without a
licence — costing them Pest and PHPStan, not just Flux. Keeping it out here is
what lets a fork run `composer install` at all.

Then record what Flux renders internally:

```bash
composer flux:internals
```

Editions that are not installed keep whatever the manifest already records, so
running this with only free Flux refreshes those names and leaves the Pro ones
alone. `composer flux:internals:check` reports drift without writing, and only
fails when the icon set has actually changed. Both accept
`-- --project=../my-app` to read some other project instead of the sidecar.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Refit! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Oneleggedswede](https://github.com/onelegstudios)
- [All Contributors](../../contributors)
- Icon artwork from [Lucide](https://lucide.dev), ISC licensed — see [`resources/icons/lucide/LICENSE`](resources/icons/lucide/LICENSE)

## License

Refit is open-sourced software licensed under the [MIT license](LICENSE.md).
