<?php

namespace Splicewire\BeamMdx;

use Illuminate\Support\Str;

/**
 * Server-side twin of the @splicewire/beam-mdx Vite plugin's draft gate. The plugin keeps
 * drafts out of the *bundle*; this keeps a stray draft file out of the *route* —
 * belt-and-suspenders, so a direct URL can't reach a draft in a non-preview environment even
 * if its file is present. Both sides read the same `beam-mdx.preview_envs` allowlist (backed
 * by the same env var), so they can never disagree on what is visible.
 */
class Mdx
{
    /** Content-name prefixes gated by a published date; anything else is always visible. */
    public static function draftablePrefixes(): array
    {
        return (array) config('beam-mdx.draftable_prefixes', ['essays/', 'broadcasts/']);
    }

    /** Is the current environment allowed to see drafts? */
    public static function previewAllowed(): bool
    {
        return in_array(
            (string) config('app.env'),
            (array) config('beam-mdx.preview_envs', []),
            true,
        );
    }

    /** Absolute path to a content file by name (e.g. 'essays/foo'), or null if absent. */
    public static function path(string $name): ?string
    {
        $root = rtrim((string) config('beam-mdx.content_path', resource_path('js/content')), '/');
        $path = $root.'/'.$name.'.mdx';

        return is_file($path) ? $path : null;
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

    /** Should this content name resolve to a page in the current environment? */
    public static function isVisible(string $name): bool
    {
        return self::path($name) !== null
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
        $root = rtrim((string) config('beam-mdx.content_path', resource_path('js/content')), '/');

        if (! is_dir($root)) {
            return [];
        }

        $drafts = [];
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
            $name = preg_replace('/\.mdx$/', '', $name);

            if (self::isDraft($name)) {
                $drafts[] = $name;
            }
        }

        sort($drafts);

        return $drafts;
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
