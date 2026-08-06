<?php

namespace Splicewire\Beam\Mdx\Console;

use Illuminate\Console\Command;
use Splicewire\Beam\Mdx\Mdx;

/**
 * `php artisan splicewire:beam:mdx:doctor` — audits the file-driven MDX content plane against the
 * locked draft-visibility decision: the plane is wired, and no draft is reachable in a
 * production build. Two independent checks, mirroring the two enforcement mechanisms:
 *
 *  1. Route gate — every draft content name must be hidden by `Mdx::isVisible()` when the
 *     current env isn't preview-allowlisted (the belt-and-suspenders to the bundle plugin).
 *  2. Bundle grep — no draft slug may appear in the built assets when the env isn't
 *     allowlisted (the build-time exclusion actually happened).
 *
 * The same two checks run for the parallel **gated** axis (`access:` content), but
 * unconditionally — gating is a confidentiality boundary, not a draft/preview one, so a
 * gated slug must never be visible or bundled in *any* env (including a preview one).
 *
 * Exits non-zero on any hard failure so CI / a deploy gate can block on it.
 */
class BeamMdxDoctorCommand extends Command
{
    protected $signature = 'splicewire:beam:mdx:doctor';

    protected $description = 'Audit the MDX content plane: wired, and no draft reachable in a production build.';

    public function handle(): int
    {
        $failed = false;
        $env = (string) config('app.env');
        $previewAllowed = Mdx::previewAllowed();
        $contentPath = rtrim((string) config('beam.mdx.content_path'), '/');

        // --- Check 1: the plane is wired ---------------------------------------------
        $mdxCount = 0;
        if (is_dir($contentPath)) {
            foreach (
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($contentPath, \FilesystemIterator::SKIP_DOTS),
                ) as $file
            ) {
                if ($file->isFile() && $file->getExtension() === 'mdx') {
                    $mdxCount++;
                }
            }
        }

        if ($mdxCount > 0) {
            $this->components->info("MDX plane wired: {$mdxCount} content file(s) under {$contentPath}.");
        } else {
            $this->components->error("MDX plane not wired: no .mdx under {$contentPath}.");
            $failed = true;
        }

        $assets = (string) config('beam.mdx.build_assets_path');

        // --- Gated axis: unconditional — a gated slug must never be visible or bundled ---
        $gated = Mdx::gatedNames();
        $reachableGated = array_values(array_filter($gated, fn (string $name) => Mdx::isVisible($name)));

        if ($reachableGated === []) {
            $this->components->info('Gate: all '.count($gated).' gated (access:) name(s) 404 on the public route.');
        } else {
            $this->components->error('Gate: gated name(s) publicly reachable: '.implode(', ', $reachableGated).'.');
            $failed = true;
        }

        if ($assets !== '' && is_dir($assets)) {
            $leakedGated = $this->slugsInBundle($assets, $gated);

            if ($leakedGated === []) {
                $this->components->info('Bundle: no gated slug found in the built assets.');
            } else {
                $this->components->error('Bundle: gated slug(s) present in the shipped build: '.implode(', ', $leakedGated).'.');
                $failed = true;
            }
        }

        $drafts = Mdx::draftNames();

        // --- Preview env: drafts are intentionally visible, skip the leak asserts ------
        if ($previewAllowed) {
            $this->components->info(
                "Env '{$env}' is preview-allowlisted: ".count($drafts).' draft(s) intentionally visible; leak checks skipped.',
            );

            return $this->finish($failed);
        }

        // --- Check 2: the route gate hides every draft --------------------------------
        $reachable = array_values(array_filter($drafts, fn (string $name) => Mdx::isVisible($name)));

        if ($reachable === []) {
            $this->components->info(
                'Route gate: all '.count($drafts)." draft(s) 404 in env '{$env}'.",
            );
        } else {
            $this->components->error(
                'Route gate: draft(s) reachable in a non-preview env: '.implode(', ', $reachable).'.',
            );
            $failed = true;
        }

        // --- Check 3: no draft slug leaked into the built bundle ----------------------
        if ($assets === '' || ! is_dir($assets)) {
            $this->components->warn(
                'Bundle check skipped: no built assets at '.($assets ?: '(unset)').' — run `npm run build` to verify exclusion.',
            );
        } else {
            $leaked = $this->slugsInBundle($assets, $drafts);

            if ($leaked === []) {
                $this->components->info('Bundle: no draft slug found in the built assets.');
            } else {
                $this->components->error(
                    'Bundle: draft slug(s) present in the shipped build: '.implode(', ', $leaked).'.',
                );
                $failed = true;
            }
        }

        return $this->finish($failed);
    }

    /**
     * The last path segment of each draft name (its URL slug), if it appears verbatim in any
     * built asset file. A hit means a draft compiled into the shipped bundle.
     *
     * @param  list<string>  $drafts
     * @return list<string>
     */
    private function slugsInBundle(string $assets, array $drafts): array
    {
        $blobs = [];
        foreach (glob($assets.'/*') ?: [] as $file) {
            if (is_file($file)) {
                $blobs[] = (string) file_get_contents($file);
            }
        }
        $haystack = implode("\n", $blobs);

        $leaked = [];
        foreach ($drafts as $name) {
            $slug = basename($name);
            if ($slug !== '' && str_contains($haystack, $slug)) {
                $leaked[] = $name;
            }
        }

        return $leaked;
    }

    private function finish(bool $failed): int
    {
        if ($failed) {
            $this->newLine();
            $this->components->error('MDX plane has blocking failures — a draft could reach production.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
