<?php

namespace Splicewire\Beam\Mdx\Convert;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;
use Splicewire\Beam\Mdx\Legal\LegalTexts;
use Splicewire\Beam\Mdx\Lens\MdxLens;

/**
 * Converts a host's MDX files once, at adoption, for one reviewed commit (ADR-0233 d9; EP-27 rulings 1 and 4).
 *
 * Each page (every `.mdx` under the root outside a `fragments/` or `partials/` directory) is read through the MDX Lens
 * and printed canonically. A `<Content name="fragments/legal-engine" />` becomes the shipped `<LegalEngine />`, and one
 * naming a host's own fragment becomes that fragment's component tag, `<ReadingDisclaimer />` (d8). A static `export
 * const meta` becomes frontmatter, its keys added as written. A page is left as it is, and reported, when its blocks
 * read back from the printed file differ from the converted ones or its visible text changes (ruling 1), when its meta
 * has no frontmatter form, or when it names a fragment no file defines. A fragment no page includes is reported, not
 * converted. The fragments themselves are never rewritten: the legal ones are shipped, and a host's own become stored
 * components with its pages (the stored-pages half, in laravel-beam-ux).
 */
final class MdxFileConverter
{
    /** The directories a fragment lives in, as the content names that place them spell them. */
    public const FRAGMENT_DIRECTORIES = ['fragments', 'partials'];

    public function __construct(private MdxLens $lens, private FrontmatterParser $frontmatter) {}

    public function convert(string $root, bool $write): MdxConversion
    {
        $root = rtrim($root, '/');
        $fragments = $pages = [];
        $report = new MdxConversion;
        ['files' => $files, 'links' => $links] = self::walk($root);
        foreach ($links as $relative) {
            $report->leftOut[$relative] = 'it is a symbolic link, which the conversion never follows';
        }
        foreach ($files as $relative) {
            $name = substr($relative, 0, -4);
            self::isFragment($relative) ? $fragments[$name] = $relative : $pages[] = $relative;
        }
        $used = [];
        foreach ($pages as $relative) {
            if (! self::inside($root, $relative)) {
                $report->leftOut[$relative] = 'it resolves outside the content root';

                continue;
            }
            $source = (string) file_get_contents("{$root}/{$relative}");
            [$frontmatter, $body] = self::split($source);
            $body = $this->convertBody($body, $fragments);
            $used += array_fill_keys(array_keys($body['placed']), true);
            [$frontmatter, $problem] = $body['problem'] === null
                ? $this->frontmatterWithMeta($frontmatter, $body['meta'], $body['metaData'])
                : [$frontmatter, $body['problem']];
            if ($problem !== null) {
                $report->leftOut[$relative] = $problem;

                continue;
            }
            $printed = $body['printed'];
            $placed = $body['placed'];
            $converted = ($frontmatter === null ? '' : "---\n{$frontmatter}\n---\n\n").$printed;
            if ($converted === $source) {
                $report->unchanged[] = $relative;

                continue;
            }
            if ($write) {
                // Checked again at the write: the file is still the one under the root that was read.
                if (! self::inside($root, $relative)) {
                    $report->leftOut[$relative] = 'it resolves outside the content root';

                    continue;
                }
                file_put_contents("{$root}/{$relative}", $converted);
            }
            $report->converted[] = $relative;
            foreach ($placed as $fragment => $component) {
                if (self::isLegal($fragment)) {
                    $report->legal[$relative][] = $component;
                } else {
                    $report->hostComponents[$fragment] = $component;
                }
            }
        }
        ksort($report->leftOut);
        $report->unusedFragments = array_values(array_diff(array_keys($fragments), array_keys($used)));
        sort($report->unusedFragments);
        ksort($report->hostComponents);

        return $report;
    }

    /**
     * One MDX body converted (d8, d9; ruling 1): read through the lens, each fragment placed as its component's tag, and
     * printed canonically, safe only when the blocks read back from the print equal the converted ones and the visible
     * text is unchanged. A stored page's body (laravel-beam-ux) converts the same way as a file's.
     *
     * @param  array<string, string>  $fragments  the content root's fragments, by content name ({@see fragmentsUnder()})
     * @return array{doc: array<int, array<string, mixed>>, printed: string, placed: array<string, string>, meta: ?string, metaData: ?array, problem: ?string}
     */
    public function convertBody(string $body, array $fragments): array
    {
        $out = ['doc' => [], 'printed' => '', 'placed' => [], 'meta' => null, 'metaData' => null, 'problem' => null];
        try {
            $read = $this->lens->read($body);
        } catch (ValidationException|\RuntimeException $failure) {
            return ['problem' => 'the lens cannot read it: '.$this->message($failure)] + $out;
        }
        $placed = [];
        $problem = null;
        $doc = $this->placeFragments($read['doc'], $fragments, $placed, $problem);
        $out = ['doc' => $doc, 'placed' => $placed, 'meta' => $read['meta'], 'metaData' => $read['metaData'] ?? null] + $out;
        if ($problem !== null) {
            return ['problem' => $problem] + $out;
        }
        $printed = $this->lens->print($doc);
        $back = $this->lens->read($printed)['doc'];
        if (! self::same($back, $doc) || self::text($back) !== self::text($read['doc'])) {
            return ['problem' => 'its blocks or visible text would change, so it is left as it is'] + $out;
        }

        return ['printed' => $printed] + $out;
    }

    /**
     * The fragments under a content root, by the content name a page places them with (`fragments/legal-engine`).
     *
     * @return array<string, string> content name => relative path
     */
    public static function fragmentsUnder(string $root): array
    {
        $out = [];
        foreach (self::walk(rtrim($root, '/'))['files'] as $relative) {
            if (self::isFragment($relative)) {
                $out[substr($relative, 0, -4)] = $relative;
            }
        }

        return $out;
    }

    /** The tag a fragment becomes: a shipped legal text's component, or the studly form of a host fragment's file name. */
    public static function componentFor(string $fragment): string
    {
        return self::isLegal($fragment) ? LegalTexts::componentName(basename($fragment)) : Str::studly(basename($fragment));
    }

    /** Whether a fragment is one of the four shipped legal texts (d8), wherever the host keeps its copy. */
    public static function isLegal(string $fragment): bool
    {
        return isset(LegalTexts::all()[basename($fragment)]);
    }

    public static function isFragment(string $relative): bool
    {
        return array_intersect(explode('/', dirname($relative)), self::FRAGMENT_DIRECTORIES) !== [];
    }

    /**
     * Every `.mdx` under the root, relative and sorted, and every `.mdx` that is a symbolic link. The walk never follows a
     * link (a linked directory is not entered), and a file is collected only when it resolves beneath the root, so the
     * conversion reads and writes nothing outside it.
     *
     * @return array{files: list<string>, links: list<string>}
     */
    public static function walk(string $root): array
    {
        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            return ['files' => [], 'links' => []];
        }
        $files = $links = [];
        $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($entries as $entry) {
            $relative = substr($entry->getPathname(), strlen($root) + 1);
            if ($entry->isLink()) {
                if (str_ends_with($relative, '.mdx')) {
                    $links[] = $relative;
                }

                continue;
            }
            if ($entry->isFile() && $entry->getExtension() === 'mdx' && self::inside($root, $relative)) {
                $files[] = $relative;
            }
        }
        sort($files);
        sort($links);

        return ['files' => $files, 'links' => $links];
    }

    /** Whether a path under the root is a plain file that resolves beneath the root's real path, never through a link. */
    private static function inside(string $root, string $relative): bool
    {
        $path = "{$root}/{$relative}";
        $real = realpath($path);
        $base = realpath($root);

        return ! is_link($path) && $real !== false && $base !== false && str_starts_with($real, rtrim($base, '/').'/');
    }

    /** @return array{0: ?string, 1: string} the frontmatter's inner text (null with none) and the body */
    public static function split(string $source): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?/s', $source, $match) !== 1) {
            return [null, $source];
        }

        return [$match[1], ltrim(substr($source, strlen($match[0])), "\r\n")];
    }

    /**
     * Each `<Content name="…">` in the blocks becomes the tag of the component its fragment is (d8).
     *
     * @param  array<string, string>  $fragments  by content name
     * @param  array<string, string>  $placed  each fragment placed, with its component (out)
     */
    private function placeFragments(array $nodes, array $fragments, array &$placed, ?string &$problem): array
    {
        foreach ($nodes as $i => $node) {
            if (($node['kind'] ?? null) !== 'block') {
                continue;
            }
            if ($node['name'] === 'Content') {
                $props = $node['props'] ?? [];
                $name = count($props) === 1 && ($props[0]['name'] ?? null) === 'name' && ($props[0]['kind'] ?? null) === 'string' ? $props[0]['value'] : null;
                if ($name === null || ($node['children'] ?? []) !== []) {
                    $problem ??= 'it has a <Content> with more than a fragment name';

                    continue;
                }
                $legal = self::isLegal($name);
                if (! $legal && ! isset($fragments[$name])) {
                    $problem ??= "it names the fragment {$name}, which no file under the content root defines";

                    continue;
                }
                $component = self::componentFor($name);
                // A tag names one component: another fragment that would become it makes the page ambiguous. Two copies of
                // one shipped legal text are that one component, the same text.
                $rivals = array_filter(array_keys($fragments), fn (string $other) => $other !== $name
                    && self::componentFor($other) === $component && ! ($legal && self::isLegal($other)));
                if ($rivals !== []) {
                    $problem ??= 'it names the fragment '.$name.', but '.implode(', ', $rivals)." would also become <{$component} />: rename one";

                    continue;
                }
                $placed[$name] = $component;
                $nodes[$i] = ['kind' => 'block', 'name' => $component, 'isComponent' => true, 'props' => [], 'children' => [], 'dynamic' => false];

                continue;
            }
            $nodes[$i]['children'] = $this->placeFragments($node['children'] ?? [], $fragments, $placed, $problem);
        }

        return $nodes;
    }

    /**
     * The frontmatter with a static meta's keys added as written, each read back by the frontmatter grammar to the value
     * it holds; a page whose meta has no such form is reported instead. A stored page's source converts the same way.
     *
     * @return array{0: ?string, 1: ?string} the frontmatter's inner text, and a problem
     */
    public function frontmatterWithMeta(?string $frontmatter, ?string $meta, ?array $metaData): array
    {
        if ($meta === null) {
            return [$frontmatter, null];
        }
        if ($metaData === null) {
            return [$frontmatter, 'its `export const meta` isn\'t static, so it has no frontmatter form'];
        }
        $existing = $this->frontmatter->parse("---\n".($frontmatter ?? '')."\n---\n")->fields;
        $lines = [];
        foreach ($metaData as $key => $value) {
            $written = self::scalar($value);
            if ($written === null || preg_match('/^[A-Za-z0-9_-]+$/', (string) $key) !== 1) {
                return [$frontmatter, "its meta's `{$key}` is a value frontmatter here can't hold (a list, an object, null, or a string with a quote or a line break)"];
            }
            $canonical = FrontmatterParser::canonicalize((string) $key);
            if (array_key_exists($canonical, $existing)) {
                if ($existing[$canonical] !== $written[1]) {
                    return [$frontmatter, "its meta and its frontmatter disagree on `{$key}`"];
                }

                continue;
            }
            $lines[] = "{$key}: {$written[0]}";
        }
        $merged = trim(implode("\n", array_filter([trim((string) $frontmatter), ...$lines], fn (string $line) => $line !== '')));
        $read = $this->frontmatter->parse("---\n{$merged}\n---\n")->fields;
        foreach ($metaData as $key => $value) {
            if (($read[FrontmatterParser::canonicalize((string) $key)] ?? null) !== self::scalar($value)[1]) {
                return [$frontmatter, "its meta's `{$key}` doesn't read back from frontmatter"];
            }
        }

        return [$merged === '' ? null : $merged, null];
    }

    /** @return array{0: string, 1: string}|null how a value is written, and how the frontmatter grammar reads it back */
    private static function scalar(mixed $value): ?array
    {
        return match (true) {
            is_bool($value) => [$value ? 'true' : 'false', $value ? 'true' : 'false'],
            is_int($value), is_float($value) => [(string) $value, (string) $value],
            is_string($value) && preg_match('/["\\\\\r\n]/', $value) !== 1 && trim($value, " \t'") === $value => ['"'.$value.'"', $value],
            default => null,
        };
    }

    /** Two blocks documents are the same, whatever order each node's keys were written in. */
    private static function same(array $a, array $b): bool
    {
        $canonical = function (mixed $value) use (&$canonical): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $value = array_map($canonical, $value);
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return $canonical($a) === $canonical($b);
    }

    /** The visible text of a blocks document: its text nodes, in order. */
    private static function text(array $nodes): string
    {
        $out = '';
        foreach ($nodes as $node) {
            $out .= ($node['kind'] ?? null) === 'text' ? (string) $node['value'] : self::text($node['children'] ?? []);
        }

        return $out;
    }

    private function message(\Throwable $failure): string
    {
        return $failure instanceof ValidationException ? (string) collect($failure->errors())->flatten()->first() : $failure->getMessage();
    }
}
