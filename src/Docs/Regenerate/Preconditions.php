<?php

namespace Splicewire\Beam\Mdx\Docs\Regenerate;

use Illuminate\Support\Facades\Http;

/**
 * Factory for the host preconditions the guides share — each returns a {@see Precondition}
 * that asserts (never provisions) one part of the setup the `regenerate*.sh` scripts assumed
 * informally, and names the missing one on failure.
 */
class Preconditions
{
    /** A file exists — e.g. a prior walkthrough artifact a guide reads its composition id from. */
    public static function fileExists(string $label, string $path): Precondition
    {
        return new Precondition(
            $label,
            fn () => is_file($path),
            "Missing required file: {$path}",
        );
    }

    /** A directory exists — e.g. the sibling repo or a working copy at its expected path. */
    public static function directoryExists(string $label, string $path): Precondition
    {
        return new Precondition(
            $label,
            fn () => is_dir($path),
            "Missing required directory: {$path}",
        );
    }

    /** A key is present and non-empty in a sibling repo's `.env` (asserted, never written). */
    public static function envKeyReadable(string $label, string $envPath, string $key): Precondition
    {
        return new Precondition(
            $label,
            function () use ($envPath, $key) {
                if (! is_file($envPath)) {
                    return false;
                }

                foreach (preg_split('/\R/', (string) file_get_contents($envPath)) ?: [] as $line) {
                    if (str_starts_with(trim($line), $key.'=')) {
                        return trim(substr(trim($line), strlen($key) + 1)) !== '';
                    }
                }

                return false;
            },
            "Missing `{$key}` in {$envPath} — the satellite must expose it for the capture.",
        );
    }

    /** The capture token is configured (non-empty). */
    public static function tokenReadable(): Precondition
    {
        return new Precondition(
            'API token configured',
            fn () => is_string(config('docs-regenerate.api_token')) && config('docs-regenerate.api_token') !== '',
            'The capture API token is empty. Set DOCS_REGENERATE_API_TOKEN (or the seeded dev token).',
        );
    }

    /** The dev platform answers an authenticated request — proof it is serving + the tenant is seeded. */
    public static function platformServing(): Precondition
    {
        $base = rtrim((string) config('docs-regenerate.platform_base_url'), '/');

        return new Precondition(
            "Dev platform serving ({$base})",
            function () use ($base) {
                try {
                    return Http::withToken((string) config('docs-regenerate.api_token'))
                        ->withOptions(['verify' => (bool) config('docs-regenerate.verify_tls')])
                        ->timeout(10)
                        ->acceptJson()
                        ->get($base.'/api/v1/splice/compositions')
                        ->successful();
                } catch (\Throwable) {
                    return false;
                }
            },
            "The dev platform did not answer at {$base}. Confirm Herd is serving it and the demo tenant is seeded.",
        );
    }

    /** An external command-line tool is on PATH. */
    public static function toolAvailable(string $tool): Precondition
    {
        return new Precondition(
            "Tool available: {$tool}",
            function () use ($tool) {
                $which = @shell_exec('command -v '.escapeshellarg($tool).' 2>/dev/null');

                return is_string($which) && trim($which) !== '';
            },
            "Required tool `{$tool}` is not on PATH.",
        );
    }
}
