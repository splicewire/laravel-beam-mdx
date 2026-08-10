> You are in **splicewire/laravel-beam-mdx** — the Laravel companion to `@splicewire/beam-mdx`.

The server-side twin of the build-time draft gate. A catch-all content/essay route macro
(`Route::beamMdxShow`) that 404s a draft slug outside the preview allowlist, a preview-only route
middleware, the shared Mdx visibility gate, and a `splicewire:beam:mdx:doctor` command asserting
the MDX plane is wired and no draft is reachable in a production build.
