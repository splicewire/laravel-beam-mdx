<?php

namespace Splicewire\Beam\Mdx\Frontmatter;

use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\DeclaresFrontmatterFields;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\Frontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\HydratesFromFrontmatter;
use Splicewire\Beam\Mdx\Frontmatter\Contracts\RetainsUnknownFields;

/**
 * The SHIPPED DEFAULT frontmatter shape — a default, **not a base class**. A consumer with its own
 * vocabulary declares its own {@see Frontmatter} and points `beam.mdx.frontmatter.shape` at it;
 * overriding is the expected path. This exists so a consumer with no opinion does not have to have one.
 *
 * It carries only what the **format** owns — `title`, `layout`, `template` — and no consumer's
 * vocabulary. beam-ux's `nav_order`/`nav_group`/`realm`/`segment` are beam-ux's facts and belong in
 * beam-ux's own shape; they arrive here through {@see unknown()} rather than being modelled.
 *
 * ## Why `SchemaIdentity` and not just `Data` (owner ruling, 2026-08-30)
 *
 * Implementing {@see SchemaIdentity} puts frontmatter on the `schemas:generate` leg: a versioned,
 * absolute `$id` and a published JSON Schema, so an authoring surface can validate and autocomplete a
 * `---` block instead of an author guessing, and a v2 is a version bump rather than a breaking parse.
 * That is the difference between a shape that is *declared* and one that is merely *typed*, and it is
 * why `laravel-beam-mdx` takes `schemastud/laravel-data-schemas` rather than plain `spatie/laravel-data`.
 * The mechanism is the same one every tower/threads Data class rides — see
 * `splicewire/laravel-beam-threads` `src/Data/ThreadData.php` for the full explanation.
 *
 * ## ⚠️ Why the mapper is PINNED, and why deleting it will look safe
 *
 * `#[MapInputName(SnakeCaseMapper::class)]` is deliberate and load-bearing. Without it this class
 * inherits `config('data.name_mapping_strategy.input')`, which **the estate's hosts do not agree on**
 * — `CamelCaseMapper` at three, `SnakeCaseMapper` at two, and absent (vendor default `null`) at 16 of
 * 21 Herd roots. An unpinned shape therefore parses the same file three different ways depending on
 * which host loaded it.
 *
 * The trap for a future reader: the flagship's global is `CamelCaseMapper`, which agrees with its own
 * `navOrder:` files, so at the one host most likely to be tested the pin looks redundant. It is not.
 * Two tests exercise this class under a global that *disagrees* with the pin, which is the only run
 * that can prove it.
 *
 * The pin is belt-and-braces rather than the primary mechanism: {@see FrontmatterParser} already
 * canonicalizes keys to snake at the grammar, so the two agree by construction. The pin defends the
 * path where a caller hands `::from()` a raw array that never went through the parser.
 */
#[MapInputName(SnakeCaseMapper::class)]
class FrontmatterData extends Data implements DeclaresFrontmatterFields, Frontmatter, HydratesFromFrontmatter, RetainsUnknownFields, SchemaIdentity
{
    /**
     * @param  array<string, string>  $leftovers  canonical keys this shape does not declare; see {@see unknown()}
     */
    public function __construct(
        public ?string $title = null,
        public ?string $layout = null,
        public ?string $template = null,
        public array $leftovers = [],
    ) {}

    /** The stable, path-style schema name — the stem of the absolute versioned `$id`. */
    public static function schemaName(): string
    {
        return 'mdx/frontmatter';
    }

    /** v1. A later reshape bumps this and freezes v1; it never edits this class in place. */
    public static function schemaVersion(): int
    {
        return 1;
    }

    /** The format-owned keys. Everything else is somebody's else's and rides {@see unknown()}. */
    public static function fieldNames(): array
    {
        return ['title', 'layout', 'template'];
    }

    /**
     * Build from a parsed block, keeping every key this shape does not declare.
     *
     * Retention is the point: frontmatter is open-ended, and several keys in this estate's own content
     * are read by an entirely different consumer — the JS plane parses these same files at build time
     * and reads `schemaType`/`navParent` camelCase from its own reader. Dropping them here would make
     * this shape unable to round-trip a file it did not fully understand.
     */
    public static function fromFrontmatter(ParsedFrontmatter $parsed): static
    {
        $declared = array_flip(static::fieldNames());

        return static::from([
            ...array_intersect_key($parsed->fields, $declared),
            'leftovers' => array_diff_key($parsed->fields, $declared),
        ]);
    }

    /** @return array<string, string> canonical key => value, for keys this shape did not declare */
    public function unknown(): array
    {
        return $this->leftovers;
    }
}
