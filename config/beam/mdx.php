<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Draft-preview environment allowlist
    |--------------------------------------------------------------------------
    |
    | The environments permitted to see draft content — essays with no published
    | date and every broadcast. A comma-separated list (e.g. "local,staging"), not
    | fixed to `local`, so a real staging deploy can review drafts while production
    | stays clean. Defaults to `local` so local authoring previews drafts out of the
    | box; set the env to an empty string to preview nowhere, or add staging.
    |
    | This is the SERVER twin of the @splicewire/beam-mdx Vite plugin's build-time
    | exclusion: both read the SAME `BEAM_MDX_PREVIEW_ENVS` env var, so the shipped
    | bundle and the route gate can never disagree on what is visible.
    |
    */
    'preview_envs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BEAM_MDX_PREVIEW_ENVS', 'local')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Draftable content-name prefixes
    |--------------------------------------------------------------------------
    |
    | Content-name prefixes gated by a published date: a file under one of these is a
    | draft when it carries no `datePublished` (or sets `draft: true`). Anything
    | outside them (root pages like about/resume) is never dated and always visible.
    | Must match the `draftablePrefixes` given to the Vite plugin and the resolver.
    |
    */
    'draftable_prefixes' => ['essays/', 'broadcasts/'],

    /*
    |--------------------------------------------------------------------------
    | Content root
    |--------------------------------------------------------------------------
    |
    | Absolute path to the authored `.mdx` tree. A content name resolves to
    | `{content_path}/{name}.mdx`. Defaults to the satellite convention.
    |
    */
    'content_path' => resource_path('js/content'),

    /*
    |--------------------------------------------------------------------------
    | Authoring API root (ADR-0124 owner-tier seam)
    |--------------------------------------------------------------------------
    |
    | The owner-tier URI prefix the HOST mounts the content-authoring endpoints
    | at, resolved client-side by the `beam.content.*` route names. beam-mdx is a
    | `Splicewire\Beam\*` package → the free `/beam` tier, domain `content`, so the
    | default is `beam/content` (→ `GET|PUT /api/v1/beam/content/{name}`). The host
    | reads this key when calling `TierRoutes::mount()`; overridable per-deploy via
    | env without touching code. The package ships the default and the (policy-free)
    | controller; the host owns the mount, the authorization policy, and the wire.
    |
    */
    'api_root' => env('BEAM_MDX_API_ROOT', 'beam/content'),

    /*
    |--------------------------------------------------------------------------
    | Built-bundle path
    |--------------------------------------------------------------------------
    |
    | Where `npm run build` writes hashed assets. The doctor greps this tree for any
    | draft slug when the current env isn't allowlisted — the belt to the plugin's
    | suspenders. Set null to skip the bundle grep (rely on the route-gate check only).
    |
    */
    'build_assets_path' => public_path('build/assets'),
];
