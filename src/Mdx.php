<?php

namespace Splicewire\Beam\Mdx;

use Illuminate\Support\Str;

/**
 * Server-side twin of the @splicewire/beam-mdx Vite plugin's draft gate. The plugin keeps
 * drafts out of the *bundle*; this keeps a stray draft file out of the *route* —
 * belt-and-suspenders, so a direct URL can't reach a draft in a non-preview environment even
 * if its file is present. Both sides read the same `beam.mdx.preview_envs` allowlist (backed
 * by the same env var), so they can never disagree on what is visible.
 *
 * The twin also carries the parallel **gated** axis: a file declaring an `access:`
 * frontmatter key is a non-public content name — kept out of the public bundle (Vite) and
 * off the public route (here), delivered only over the host's own authenticated gate route.
 * "Gated" is deliberately **opaque**: the package holds the raw tokens and never interprets
 * them (no RBAC, no notion of "guide"); the host reads {@see gate()} and decides.
 */
class Mdx
{
    /** Content-name prefixes gated by a published date; anything else is always visible. */
    public static function draftablePrefixes(): array
    {
        return (array) config('beam.mdx.draftable_prefixes', ['essays/', 'broadcasts/']);
    }

    /** Is the current environment allowed to see drafts? */
    public static function previewAllowed(): bool
    {
        return in_array(
            (string) config('app.env'),
            (array) config('beam.mdx.preview_envs', []),
            true,
        );
    }

    /** Absolute path to a content file by name (e.g. 'essays/foo'), or null if absent. */
    public static function path(string $name): ?string
    {
        $root = rtrim((string) config('beam.mdx.content_path', resource_path('js/content')), '/');
        $path = $root.'/'.$name.'.mdx';

        return is_file($path) ? $path : null;
    }

    /**
     * Write raw MDX bytes to a content name's `.mdx` file, creating parent directories as
     * needed, and return the absolute path written. This is the author-side twin of the read
     * gate: it is deliberately **policy-free about *who*** (the host authorizes the caller),
     * but owns two hard invariants no caller can bypass —
     *
     *  1. **Preview-env only.** Authoring is a preview/local capability, never production; a
     *     write is a hard no-op (throws) when {@see previewAllowed()} is false, mirroring the
     *     draft/gate read guards. Content is git-file-backed, not a production CMS store.
     *  2. **Containment.** The name must resolve to a file *under* the content root — traversal
     *     (`../`), absolute paths, NUL bytes, and symlink-escapes are rejected before any write.
     *
     * @throws \RuntimeException when previews are disallowed (production hard stop)
     * @throws \InvalidArgumentException when the name escapes the content root
     */
    public static function write(string $name, string $raw): string
    {
        if (! self::previewAllowed()) {
            throw new \RuntimeException('Content authoring is disabled outside preview environments.');
        }

        $target = self::writablePath($name);

        $dir = \dirname($target);
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($target, $raw);

        return $target;
    }

    /**
     * Resolve a content name to the absolute `.mdx` path a write MUST land at, or throw when
     * the name would escape the content root. Unlike {@see path()} this does not require the
     * file to exist yet (a write may create it); it enforces *containment* only. Two layers:
     * a syntactic reject of obvious escapes, then a canonicalised belt — the deepest existing
     * ancestor of the target must resolve inside the (real) content root, so a symlinked
     * subtree can't smuggle the write out.
     */
    private static function writablePath(string $name): string
    {
        if (
            $name === ''
            || str_contains($name, "\0")
            || str_starts_with($name, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $name) === 1
        ) {
            throw new \InvalidArgumentException("Unsafe content name: {$name}");
        }

        $root = rtrim((string) config('beam.mdx.content_path', resource_path('js/content')), '/');
        $canonicalRoot = realpath($root);

        if ($canonicalRoot === false) {
            throw new \RuntimeException("Content root does not exist: {$root}");
        }

        $target = $root.'/'.$name.'.mdx';

        // Canonicalise the deepest already-existing ancestor and assert it sits under the root.
        $ancestor = $target;
        while (! file_exists($ancestor)) {
            $parent = \dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }

        $canonicalAncestor = realpath($ancestor);
        if ($canonicalAncestor === false
            || ! str_starts_with($canonicalAncestor.'/', $canonicalRoot.'/')
        ) {
            throw new \InvalidArgumentException("Content name escapes the content root: {$name}");
        }

        return $target;
    }

    /**
     * A file is a draft when it's a dated content type (essay/broadcast) carrying no
     * `datePublished`, or when it sets `draft: true`. Broadcasts never carry a date, so they
     * are structurally always draft. Non-dated pages (about, resume) are never drafts.
     */
    public static function isDraft(string $name): bool
    {
        $path = self::path($name);

        if ($path === null) {
            return false;
        }

        $frontmatter = self::frontmatter($path);

        // Match any YAML-truthy `draft:` form so this gate can't disagree with the
        // frontmatter parser that renders the page.
        if (in_array(strtolower((string) ($frontmatter['draft'] ?? '')), ['true', 'yes', '1'], true)) {
            return true;
        }

        if (! Str::startsWith($name, self::draftablePrefixes())) {
            return false;
        }

        return empty($frontmatter['datePublished']);
    }

    /**
     * A gated file declares an `access:` frontmatter key. It is a non-public content
     * name — never in the public bundle, never on the public route — regardless of the
     * preview allowlist (gating is a confidentiality boundary, not a draft/preview one).
     */
    public static function isGated(string $name): bool
    {
        return self::gate($name) !== null;
    }

    /**
     * The opaque any-of `access:` tokens a file declares, or null when the file is absent
     * or ungated. Tokens are returned verbatim for the host to interpret; a scalar becomes
     * a one-element list, an inline `[a, b]` flow list is split. A present-but-empty
     * `access:` returns `[]` (still gated — the host denies an empty any-of), never null.
     *
     * @return list<string>|null
     */
    public static function gate(string $name): ?array
    {
        $path = self::path($name);

        if ($path === null) {
            return null;
        }

        $frontmatter = self::frontmatter($path);

        if (! array_key_exists('access', $frontmatter)) {
            return null;
        }

        return self::tokens((string) $frontmatter['access']);
    }

    /**
     * The opaque any-of `entitlement:` tokens a file declares (the parallel plan axis),
     * or null when absent/ungated. Read alongside {@see gate()} by the host; wired now,
     * unused by any content today.
     *
     * @return list<string>|null
     */
    public static function entitlement(string $name): ?array
    {
        $path = self::path($name);

        if ($path === null) {
            return null;
        }

        $frontmatter = self::frontmatter($path);

        if (! array_key_exists('entitlement', $frontmatter)) {
            return null;
        }

        return self::tokens((string) $frontmatter['entitlement']);
    }

    /** The flat scalar frontmatter of a content name (host-facing reader), or `[]` if absent. */
    public static function fields(string $name): array
    {
        $path = self::path($name);

        return $path === null ? [] : self::frontmatter($path);
    }

    /** Should this content name resolve to a page in the current environment? */
    public static function isVisible(string $name): bool
    {
        return self::path($name) !== null
            && ! self::isGated($name)
            && (self::previewAllowed() || ! self::isDraft($name));
    }

    /**
     * Every draft content name under the content root (recursive scan of `.mdx` files).
     * Used by the doctor to enumerate what must not leak into a production build.
     *
     * @return list<string>
     */
    public static function draftNames(): array
    {
        return array_values(array_filter(self::names(), self::isDraft(...)));
    }

    /**
     * Every gated content name under the content root — the twin of {@see draftNames()}
     * for the `access:` axis, so the doctor can prove no gated slug leaks into the bundle.
     *
     * @return list<string>
     */
    public static function gatedNames(): array
    {
        return array_values(array_filter(self::names(), self::isGated(...)));
    }

    /**
     * Every content name under the content root (recursive scan of `.mdx` files), sorted.
     * Optionally restricted to those under a name prefix (e.g. `docs/`). Host-facing: the
     * enumerator a contributor walks to build a server-authoritative index.
     *
     * @return list<string>
     */
    public static function names(?string $under = null): array
    {
        $root = rtrim((string) config('beam.mdx.content_path', resource_path('js/content')), '/');

        if (! is_dir($root)) {
            return [];
        }

        $names = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'mdx') {
                continue;
            }

            $name = str_replace('\\', '/', ltrim(
                substr($file->getPathname(), strlen($root)),
                '/',
            ));
            $name = (string) preg_replace('/\.mdx$/', '', $name);

            if ($under !== null && ! Str::startsWith($name, $under)) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    /**
     * Split an `access:`/`entitlement:` frontmatter value into opaque tokens. A bare scalar
     * (`root`) becomes a one-element list; an inline flow list (`[root, activity.view]`) is
     * split on commas. Tokens are trimmed of surrounding quotes/space; empties are dropped.
     *
     * @return list<string>
     */
    private static function tokens(string $raw): array
    {
        $raw = trim($raw);

        if (Str::startsWith($raw, '[') && Str::endsWith($raw, ']')) {
            $raw = substr($raw, 1, -1);
        }

        return array_values(array_filter(
            array_map(fn (string $t): string => trim($t, " \t\"'"), explode(',', $raw)),
            fn (string $t): bool => $t !== '',
        ));
    }

    /** Flat scalar frontmatter from the leading `---` block — only what the gate needs. */
    private static function frontmatter(string $path): array
    {
        $source = (string) file_get_contents($path);

        if (! preg_match('/^---\r?\n(.*?)\r?\n---/s', $source, $match)) {
            return [];
        }

        $fields = [];
        foreach (preg_split('/\r?\n/', $match[1]) as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $kv)) {
                $fields[$kv[1]] = trim($kv[2], " \t\"'");
            }
        }

        return $fields;
    }
}
