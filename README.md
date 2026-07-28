# splicewire/laravel-beam-mdx

The Laravel companion to `@splicewire/beam-mdx` — the server-side twin of the build-time draft
gate. Where the npm plugin keeps drafts out of the *bundle*, this keeps a stray draft file out
of the *route*, and ships a doctor that asserts neither leaked.

## What it provides

- **Route macros** — collapse each app's hand-written gated slug route:
  - `Route::beamMdxShow('essays', 'content/show')->name('essays.show')` — registers a gated
    `GET /essays/{slug}` that 404s a draft slug outside the preview allowlist, else renders the
    Inertia page with the fully-qualified content name.
  - `Route::beamMdxPage('/about', 'content/show', 'about')` — a single gated named page.
- **`beam-mdx.preview` middleware** — fence a whole always-draft surface (e.g. the broadcasts
  ledger) to preview-allowlisted environments.
- **`Splicewire\Beam\Mdx\Mdx`** — the visibility gate (`isVisible`, `isDraft`, `previewAllowed`,
  `draftNames`), reading `config/beam-mdx.php`.
- **`php artisan beam-mdx:doctor`** — audits the plane against the locked decision: the plane is
  wired, the route gate hides every draft in a non-preview env, and no draft slug appears in the
  built assets. Exits non-zero on any failure so CI / a deploy gate can block on it.

## Config

`config/beam-mdx.php` — `preview_envs` (from `BEAM_MDX_PREVIEW_ENVS`, the same var the Vite
plugin reads, so bundle and route can't disagree), `draftable_prefixes`, `content_path`,
`build_assets_path`.

## Install

```jsonc
// composer.json
"require": { "splicewire/laravel-beam-mdx": "dev-main" },
"repositories": [{ "type": "git", "url": "https://github.com/splicewire/laravel-beam-mdx.git" }]
```

The service provider auto-discovers; publish the config with
`php artisan vendor:publish --tag=laravel-beam-mdx-config` to override defaults.
