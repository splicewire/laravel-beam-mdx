<?php

namespace Splicewire\Beam\Mdx\Doctor;

use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mdx\Mdx;

/**
 * The MDX content-plane checks, extracted from the doctor command into the shared finding
 * vocabulary (particle-doctrine-followups ticket 08). Audits the file-driven MDX content plane
 * against the locked draft-visibility decision: the plane is wired, and no draft is reachable in a
 * production build. Two independent checks, mirroring the two enforcement mechanisms:
 *
 *  1. Route gate — every draft content name must be hidden by `Mdx::isVisible()` when the
 *     current env isn't preview-allowlisted (the belt-and-suspenders to the bundle plugin).
 *  2. Bundle grep — no draft slug may appear in the built assets when the env isn't
 *     allowlisted (the build-time exclusion actually happened).
 *
 * The same two checks run for the parallel **gated** axis (`access:` content), but
 * unconditionally — gating is a confidentiality boundary, not a draft/preview one, so a
 * gated slug must never be visible or bundled in *any* env (including a preview one). In a
 * preview-allowlisted env the draft leak checks are skipped (drafts are intentionally visible)
 * and a Pass finding says so.
 *
 * **Standing on borrowed time, deliberately** (beam-docs-satellite ticket 06 / ADR-0209 Consequences).
 * Both checks guard the BUILD-TIME MDX bundle: `Mdx::isVisible`'s bundle-exclusion draft gate and a
 * host's build-time content glob. ADR-0209's compile-on-save replaces both with `workflow_marking` +
 * `EntryPublishGate` + the two-right access model — but only for content that has been CONVERTED to
 * entries. Until a host's tracks convert (ticket 18), the file-driven plane is still live and these are
 * still the only thing standing between a draft and a production bundle, so retiring the audit with the
 * renderer would remove a real guard on the strength of an argument about the future.
 *
 * Check names + details concatenate to the exact lines the command always printed
 * (`<check>: <detail>`), so extraction is provably behavior-preserving. Zero-arg and
 * container-resolvable, so the shared DoctorRunner and the beam doctor manifest can `make()` it.
 */
class MdxContentPlaneAudit implements DoctorAudit
{
    /**
     * @return list<Finding>
     */
    public function run(): array
    {
        $findings = [];
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

        // Expectation only qualifies an empty inventory; it never disables guards on actual files.
        if ($mdxCount === 0 && config('beam.mdx.file_content_expected', true) === false) {
            return [Finding::inconclusive(
                'MDX plane not applicable',
                "beam.mdx.file_content_expected=false and no .mdx under {$contentPath}; no file content to audit.",
            )];
        }

        $findings[] = $mdxCount > 0
            ? Finding::pass('MDX plane wired', "{$mdxCount} content file(s) under {$contentPath}.")
            : Finding::fail('MDX plane not wired', "no .mdx under {$contentPath}.");

        $assets = (string) config('beam.mdx.build_assets_path');

        // --- Gated axis: unconditional — a gated slug must never be visible or bundled ---
        $gated = Mdx::gatedNames();
        $reachableGated = array_values(array_filter($gated, fn (string $name) => Mdx::isVisible($name)));

        $findings[] = $reachableGated === []
            ? Finding::pass('Gate', 'all '.count($gated).' gated (access:) name(s) 404 on the public route.')
            : Finding::fail('Gate', 'gated name(s) publicly reachable: '.implode(', ', $reachableGated).'.');

        if ($assets !== '' && is_dir($assets)) {
            $leakedGated = $this->slugsInBundle($assets, $gated);

            $findings[] = $leakedGated === []
                ? Finding::pass('Bundle', 'no gated slug found in the built assets.')
                : Finding::fail('Bundle', 'gated slug(s) present in the shipped build: '.implode(', ', $leakedGated).'.');
        }

        $drafts = Mdx::draftNames();

        // --- Preview env: drafts are intentionally visible, skip the leak asserts ------
        if ($previewAllowed) {
            $findings[] = Finding::pass(
                "Env '{$env}' is preview-allowlisted",
                count($drafts).' draft(s) intentionally visible; leak checks skipped.',
            );

            return $findings;
        }

        // --- Check 2: the route gate hides every draft --------------------------------
        $reachable = array_values(array_filter($drafts, fn (string $name) => Mdx::isVisible($name)));

        $findings[] = $reachable === []
            ? Finding::pass('Route gate', 'all '.count($drafts)." draft(s) 404 in env '{$env}'.")
            : Finding::fail('Route gate', 'draft(s) reachable in a non-preview env: '.implode(', ', $reachable).'.');

        // --- Check 3: no draft slug leaked into the built bundle ----------------------
        if ($assets === '' || ! is_dir($assets)) {
            $findings[] = Finding::warn(
                'Bundle check skipped',
                'no built assets at '.($assets ?: '(unset)').' — run `npm run build` to verify exclusion.',
            );
        } else {
            $leaked = $this->slugsInBundle($assets, $drafts);

            $findings[] = $leaked === []
                ? Finding::pass('Bundle', 'no draft slug found in the built assets.')
                : Finding::fail('Bundle', 'draft slug(s) present in the shipped build: '.implode(', ', $leaked).'.');
        }

        return $findings;
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
}
