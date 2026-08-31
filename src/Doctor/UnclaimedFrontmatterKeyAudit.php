<?php

namespace Splicewire\Beam\Mdx\Doctor;

use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\DeclaresFrontmatterFields;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterResolver;

/**
 * Reports an authored frontmatter key that, after canonicalization, **no declared shape claims**
 * (frontmatter-declaration-seam ticket 06) — the instrument that would have found this charter's
 * founding defect on its own.
 *
 * That defect was invisible to every existing gate: `navOrder:` parsed cleanly, imported cleanly,
 * returned success, and was dropped. Nothing errored because nothing was *asking* whether a key
 * reached a reader. This asks.
 *
 * ## ADVISORY, never fatal — and that is a rule, not a courtesy
 *
 * Which keys a host's authors write is a fact about the **host**, so per `AGENTS.md` it cannot throw.
 * An unclaimed key is very often perfectly legitimate: this estate's own content authors `schemaType`,
 * `navParent` and `navGroupOrder`, which the **JS plane** reads at build time from its own parser and
 * which no PHP shape has any business claiming. The audit reports the population; a human reads it.
 *
 * ## A zero has to say what it counted
 *
 * A shape that declines {@see DeclaresFrontmatterFields} cannot be audited — it has not said what it
 * claims — so it is skipped, and the finding **says how many shapes were skipped**. A bare "no
 * unclaimed keys" is indistinguishable from "nothing was checked", which is the reporting failure this
 * estate keeps paying for.
 */
class UnclaimedFrontmatterKeyAudit implements DoctorAudit
{
    /** @return list<Finding> */
    public function run(): array
    {
        $contentPath = rtrim((string) config('beam.mdx.content_path'), '/');

        if (! is_dir($contentPath)) {
            return [Finding::pass('Frontmatter keys', "no content tree at {$contentPath}; nothing to check.")];
        }

        $shape = $this->shapeClass();

        if ($shape === null) {
            return [Finding::warn(
                'Frontmatter keys',
                'no frontmatter shape is declared ('.FrontmatterResolver::CONFIG_KEY.'), so no key can be checked.'
            )];
        }

        if (! is_subclass_of($shape, DeclaresFrontmatterFields::class)) {
            return [Finding::pass(
                'Frontmatter keys',
                "shape [{$shape}] declines DeclaresFrontmatterFields, so its keys were not checked (1 shape skipped)."
            )];
        }

        $claimed = array_flip($shape::fieldNames());
        $parser = app(FrontmatterParser::class);
        $unclaimed = [];
        $files = 0;

        foreach ($this->sources($contentPath) as $path) {
            $files++;
            $parsed = $parser->parse((string) file_get_contents($path));

            foreach ($parsed->raw as $authored => $_) {
                $canonical = FrontmatterParser::canonicalize($authored);

                if (! isset($claimed[$canonical])) {
                    $unclaimed[$authored] = ($unclaimed[$authored] ?? 0) + 1;
                }
            }
        }

        if ($unclaimed === []) {
            return [Finding::pass(
                'Frontmatter keys',
                "every key across {$files} file(s) is claimed by [{$shape}]; 0 shapes skipped."
            )];
        }

        ksort($unclaimed);
        $rendered = implode(', ', array_map(
            static fn (string $key, int $n): string => "{$key} ({$n})",
            array_keys($unclaimed),
            $unclaimed
        ));

        return [Finding::warn(
            'Frontmatter keys',
            "{$files} file(s) checked against [{$shape}]; key(s) no shape claims: {$rendered}. "
            .'Advisory — a key read by another consumer (the JS plane reads several) is legitimate.'
        )];
    }

    /** The declared shape, or null when the host declares none. Never throws — this is an audit. */
    private function shapeClass(): ?string
    {
        try {
            return app(FrontmatterResolver::class)->shapeClass();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** @return iterable<string> */
    private function sources(string $root): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->isFile() && preg_match('/\.mdx?$/', $file->getFilename())) {
                yield $file->getPathname();
            }
        }
    }
}
