<?php

namespace Splicewire\Beam\Mdx\Tests\Lens\PrintStability;

use Splicewire\Beam\Mdx\Lens\MdxLens;
use Splicewire\Beam\Mdx\MdxBody;

/**
 * The lens-print stability corpus: real docs bodies (registry pages and www's foundations and laravel pages, copied
 * verbatim with their frontmatter) and the print the lens gives each one. Both halves read this one definition, so
 * neither can take a different input or a different pipeline:
 *
 * - {@see LensPrintStabilityTest} asserts every committed print and never writes;
 * - `composer lens-print:regenerate` (tests/Lens/PrintStability/regenerate.php) rewrites the prints, and only with
 *   `--write`.
 *
 * Why the print is pinned: a blocks row's `asserted_hash` is taken over the lens's print of its stored blocks
 * (splicewire/laravel-beam-ux `Reasserter::stampWritten`). If the same blocks print differently after a lens upgrade,
 * every blocks row reads as edited, and nothing re-asserts from disk again.
 */
final class LensPrintCorpus
{
    /** The environment variable naming a host root whose node_modules hold `@splicewire/beam-mdx` built. */
    public const HOST_ENV = 'BEAM_MDX_LENS_HOST';

    public const RESTAMP_NOTE = <<<'TXT'
        The MDX lens print changed. A blocks row's asserted_hash is taken over this print, so a print change re-hashes
        EVERY blocks row: each one then reads as edited and stops re-asserting from disk. If the change is deliberate,
        ship it as a reviewed update: regenerate the expectations (`composer lens-print:regenerate -- --host=<root>
        --write`), review the diff, and carry a re-stamp note telling each host to run
        `splicewire:beam:ux:register-from-disk --restamp --sources` (dry run first, then --apply) once the new lens
        is installed. Check that verb first: its second guard compares asserted_hash with the hash of the raw origin
        file, and a row stamped over the print (every row registered since beam-ux stamps the written body) fails
        it, so the verb must accept a row whose stored blocks are still its file's lowering before it can repair a
        print change. If the change is not deliberate, the lens regressed: fix it.
        TXT;

    public static function directory(): string
    {
        return __DIR__.'/../fixtures/print-stability';
    }

    /**
     * Each corpus item's slug, in the committed order.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        /** @var array<string, array{origin: string, at: string}> $corpus */
        $corpus = json_decode((string) file_get_contents(self::directory().'/corpus.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_keys($corpus);
    }

    public static function sourcePath(string $slug): string
    {
        return self::directory()."/{$slug}.source.mdx";
    }

    public static function printPath(string $slug): string
    {
        return self::directory()."/{$slug}.print.mdx";
    }

    /** The page body a registration lowers: the file less its frontmatter, as `RegisterEntriesFromDisk::lowerable` takes it. */
    public static function body(string $slug): string
    {
        return (string) MdxBody::encode((string) file_get_contents(self::sourcePath($slug)))[MdxBody::CONTENT_KEY];
    }

    /** `print(lower(body))`: the stored form a registration writes, as `Reasserter::currentBody` reads it back. */
    public static function printOnce(MdxLens $lens, string $body): string
    {
        return $lens->print($lens->read($body)['doc']);
    }

    /** The host root from the environment, or null when it does not hold the lens built. */
    public static function host(): ?string
    {
        $host = getenv(self::HOST_ENV);

        return is_string($host) && is_file($host.'/node_modules/@splicewire/beam-mdx/package.json') ? $host : null;
    }

    /** Which lens a run printed with: the host's `@splicewire/beam-mdx` version and real path. */
    public static function lensIdentity(string $host): string
    {
        $dir = $host.'/node_modules/@splicewire/beam-mdx';
        $manifest = json_decode((string) file_get_contents($dir.'/package.json'), true);

        return '@splicewire/beam-mdx@'.($manifest['version'] ?? '?').' at '.(realpath($dir) ?: $dir);
    }
}
