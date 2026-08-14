# Docs site plan — website repository

The site-side half of publishing refit's docs: one Laravel app serving several
packages' documentation under one layout. Companion to
[go-live.md](go-live.md); they meet where the publisher pushes markdown in.

Move this file to the website repo when work starts — it is here only because
that repo did not exist when the plan was written (2026-08-14).

Every config key and class named below was read from `petebishwhip/laradocs`
v1 as installed in the refit repo. Verify them against the version the site
actually installs before trusting them.

---

## 0. Prerequisites

- [ ] A Laravel app for onelegstudios.com (existing or new)
- [ ] `composer require petebishwhip/laradocs` — a real dependency here, not
      dev-only as it is in the package repo
- [ ] `php artisan vendor:publish --tag=laradocs-config`

## 1. Own the routes

Laradocs registers its own routes by default; for multiple projects the app has
to register them once per project instead.

```php
// config/laradocs.php
'route' => [
    'register' => false,   // the app wires them, see routes/docs.php
    ...
],
```

```php
// routes/docs.php — registered from RouteServiceProvider or bootstrap/app.php
use Illuminate\Contracts\Routing\Registrar;
use Laradocs\Routing\DocumentRouter;

foreach (['refit'] as $project) {          // add packages here
    (new DocumentRouter)->register(app(Registrar::class), [
        'prefix'     => "open-source/{$project}/docs",
        'name'       => "{$project}.docs.",
        'middleware' => ['web', "docs.project:{$project}"],
    ]);
}
```

`DocumentRouter::register()` is public and takes the route config as an
argument, so this gets the whole route set per project — pages, search API,
assets, sitemap, feed, robots, tags, OG images — correctly named, rather than
hand-rolling a subset.

- [ ] Confirm `route:list` shows one full set per project

## 2. The project middleware

The switch. Every one of these keys is read per request by the package
(`FilesystemLoader` takes its path as a closure; `CacheKey::prefix()` and
`DocumentUrl::prefix()` re-read config on each call), which is what makes one
app able to serve many projects.

```php
final class SetDocsProject
{
    public function handle(Request $request, Closure $next, string $project): Response
    {
        config([
            // Project root, NOT the version directory — versioning appends
            // {version} itself via Version::pathFor().
            'laradocs.docs.path'        => base_path("docs-sources/{$project}"),

            // Must match the route-name prefix, or generated links point at
            // whichever project registered its routes last.
            'laradocs.route.name'       => "{$project}.docs.",

            // Isolates rendered HTML, the nav tree and the version list.
            'laradocs.cache.key_prefix' => "laradocs:{$project}",

            // Isolates the search index.
            'laradocs.search.index'     => "laradocs-{$project}",

            'laradocs.versions.enabled' => true,   // per project
            'laradocs.ui.brand.title'   => ucfirst($project),
        ]);

        return $next($request);
    }
}
```

- [ ] Register the alias `docs.project`
- [ ] Add per-project branding as more packages arrive (title, logo, accent)

**This is the one piece coupled to package internals.** If a Laradocs upgrade
moves these keys, this middleware is what breaks. Keep it small and re-read the
upgrade notes.

## 3. The layout

```bash
php artisan vendor:publish --tag=laradocs-views
```

`show.blade.php` does `@extends('laradocs::layout')` and fills
`@section('content')` and `@section('toc')`, so the published
`resources/views/vendor/laradocs/layout.blade.php` is the single file to
rewrite: extend the site layout, and keep the `@include`s for
`laradocs::partials.header`, `partials.sidebar` and `partials.toc` — they take
their data explicitly and compose fine inside your own chrome.

- [ ] Rewrite the published layout to extend the site layout
- [ ] Keep the docs assets loading (`_laradocs/asset/*`) — the sidebar, palette
      and TOC need them
- [ ] Tune what config already covers before overriding more views: accent,
      fonts, brand, header links, footer, banner, presets, content width,
      `--dc-*` custom properties

> Publishing views takes a snapshot. Upstream layout changes will not reach the
> copy on upgrade — the package warns about exactly this for assets. Publish
> the fewest templates that do the job.

## 4. Content and credentials

- [ ] Create `docs-sources/refit/` — the publisher writes `v0/`, `v1/` … inside
      it, each with a `_version.json`
- [ ] Create a token with `contents: write` on this repo and hand it to refit as
      `secrets.DOCS_SITE_TOKEN` (see go-live.md step 5)
- [ ] Decide whether `docs-sources/` is committed (simplest — the publisher
      commits into it) or built at deploy time

## 5. Deploy pipeline

- [ ] Use a real cache store for docs (`laradocs.cache.store`). The workbench
      hit `no such table: cache` on the database driver — the site needs redis
      or file, or the docs commands fail on a cold deploy
- [ ] Warm each project *and version* on deploy — the commands read the active
      config, so loop with the same env the middleware would set:

      LARADOCS_PATH=docs-sources/refit php artisan laradocs:cache
      LARADOCS_PATH=docs-sources/refit php artisan laradocs:index

- [ ] Confirm a push from refit's publisher triggers a site deploy (Forge/Cloud
      auto-deploy on push, or a webhook)

## 6. Verification checklist

- [ ] `/open-source/refit/docs` redirects or renders the default version per
      `versions.unversioned`
- [ ] `/open-source/refit/docs/v0/guide/icons` renders, sidebar highlights it
- [ ] Version selector lists every published line, labelled with its exact tag
- [ ] Search returns refit pages only — proves `cache.key_prefix` and
      `search.index` isolation once a second project exists
- [ ] `sitemap.xml`, `robots.txt` and `feed.xml` resolve under each project's
      prefix
- [ ] Site chrome renders on docs pages, and docs chrome does not leak onto
      site pages
- [ ] A second project added to the loop needs no code changes beyond the array

## 7. Known wrinkles

- **Edit-this-page needs a path fix.** `ui.edit.url` interpolates `{file}`
  relative to the docs root, which here is `docs-sources/refit`, so `{file}`
  arrives as `v0/using-refit/guide/icons.md`. Pointing that at the refit repo
  (where the file is `docs/using-refit/guide/icons.md`) means stripping the
  version segment — a small
  view override, or leave the edit link off until it matters.
- **Two sitemaps, one site.** Each project prefix serves its own. Make sure the
  site's root sitemap references them rather than competing.
- **Versioning is per project, not per site.** `versions.enabled` is set in the
  middleware, so a package with one version can stay unversioned.
- **Do not reach for `_shared`** (the cross-version shared-pages directory)
  unless a page genuinely must be identical across versions; the publisher
  writes whole version trees and would not manage it.
