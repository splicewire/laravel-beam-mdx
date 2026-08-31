<?php

namespace Splicewire\Beam\Mdx\Frontmatter;

use Illuminate\Support\Str;

/**
 * The ONE implementation of what a `---` block means (frontmatter-declaration-seam ticket 01).
 *
 * Before this class the same two regexes lived in five places — `Mdx`, `MdxBody`, beam-ux's
 * `RegisterEntriesFromDisk` and `StubContent`, and beam-mcp's `McpDocsSeeder` — and `MdxBody`'s
 * docblock claimed they "can never disagree". They already did: four consumed the trailing newline
 * after the closing fence and one did not.
 *
 * ## Why keys are canonicalized here and not at the consumer
 *
 * The key pattern admits camelCase (`[A-Za-z0-9_-]+` matches `navOrder` perfectly well), so a reader
 * looking up `nav_order` found nothing, dropped the field, and returned success. There was no parse
 * error to notice — the flagship authored 23 `navOrder:` and 26 `navGroup:` files against readers
 * that only ever looked up the snake spelling.
 *
 * Canonicalizing at the GRAMMAR is the right layer because `navOrder:` and `nav_order:` mean the same
 * thing *in the file*, before any consumer has an opinion about which fields exist. Doing it in each
 * consumer would put the same rule in five places again, which is the defect this class removes.
 *
 * A `spatie/laravel-data` name mapper cannot do this job: every built-in mapper resolves a property to
 * exactly ONE input key (`NameMapper::map()` returns a single string), so no mapper accepts two
 * spellings. A declared shape still pins its mapper — that is what keeps host `config/data.php` out
 * of the path — but tolerance for the authored spelling belongs here.
 *
 * Deliberately NOT a YAML parser: the grammar stays flat `key: value` scalars, exactly what the five
 * readers implemented. Nested frontmatter is a separate decision with a real dependency attached.
 */
class FrontmatterParser
{
    /**
     * The leading `---` … `---` block. This is the four-copy variant, which consumes the newline
     * after the closing fence so {@see ParsedFrontmatter::$content} starts at the body. `Mdx`'s
     * one-off variant omitted that trailing `\r?\n?`; it discarded the content entirely, so the
     * difference was inert there and collapsing onto this form loses nothing.
     */
    protected const BLOCK = '/^---\r?\n(.*?)\r?\n---\r?\n?/s';

    /** One flat `key: value` line. Admits camelCase and hyphens — {@see canonicalize()} folds them. */
    protected const LINE = '/^([A-Za-z0-9_-]+):\s*(.*)$/';

    /** Read one source's `---` block. A source with no block yields no fields and its own text. */
    public function parse(string $source): ParsedFrontmatter
    {
        if (! preg_match(self::BLOCK, $source, $match)) {
            return new ParsedFrontmatter(content: $source);
        }

        $fields = [];
        $raw = [];

        foreach (preg_split('/\r?\n/', $match[1]) as $line) {
            if (! preg_match(self::LINE, $line, $kv)) {
                continue;
            }

            $value = trim($kv[2], " \t\"'");

            $raw[$kv[1]] = $value;
            $fields[self::canonicalize($kv[1])] = $value;
        }

        return new ParsedFrontmatter(
            fields: $fields,
            raw: $raw,
            content: substr($source, strlen($match[0])),
        );
    }

    /**
     * Fold an authored key to its canonical form: `snake_case`.
     *
     * Snake rather than camel because the two readers that actually consume fields today both read
     * snake, and because the columns these land in are snake — canonicalizing toward the destination
     * means the mapping is the identity at the write, not one more place to get it wrong.
     *
     * Hyphens are normalized first: `Str::snake()` does not treat `-` as a word boundary, so
     * `nav-group` would otherwise survive as its own distinct key.
     */
    public static function canonicalize(string $key): string
    {
        return Str::snake(str_replace('-', '_', $key));
    }
}
