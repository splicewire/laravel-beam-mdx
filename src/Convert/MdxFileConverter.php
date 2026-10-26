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
        foreach ($this->files($root) as $relative) {
            $name = substr($relative, 0, -4);
            self::isFragment($relative) ? $fragments[$name] = $relative : $pages[] = $relative;
        }
        $report = new MdxConversion;
        $used = [];
        foreach ($pages as $relative) {
            $source = (string) file_get_contents("{$root}/{$relative}");
            [$frontmatter, $body] = $this->split($source);
            try {
                $read = $this->lens->read($body);
            } catch (ValidationException|\RuntimeException $failure) {
                $report->leftOut[$relative] = 'the lens cannot read it: '.$this->message($failure);

                continue;
            }
            $placed = [];
            $problem = null;
            $doc = $this->placeFragments($read['doc'], $fragments, $placed, $problem);
            $used += array_fill_keys(array_keys($placed), true);
            [$frontmatter, $problem] = $problem === null ? $this->frontmatter($frontmatter, $read['meta'], $read['metaData'] ?? null) : [$frontmatter, $problem];
            if ($problem !== null) {
                $report->leftOut[$relative] = $problem;

                continue;
            }
            $printed = $this->lens->print($doc);
            $back = $this->lens->read($printed)['doc'];
            if (! self::same($back, $doc) || self::text($back) !== self::text($read['doc'])) {
                $report->leftOut[$relative] = 'its blocks or visible text would change, so it is left as it is';

                continue;
            }
            $converted = ($frontmatter === null ? '' : "---\n{$frontmatter}\n---\n\n").$printed;
            if ($converted === $source) {
                $report->unchanged[] = $relative;

                continue;
            }
            if ($write) {
                file_put_contents("{$root}/{$relative}", $converted);
            }
            $report->converted[] = $relative;
            foreach ($placed as $fragment => $component) {
                if (isset(LegalTexts::all()[basename($fragment)])) {
                    $report->legal[$relative][] = $component;
                } else {
                    $report->hostComponents[$fragment] = $component;
                }
            }
        }
        $report->unusedFragments = array_values(array_diff(array_keys($fragments), array_keys($used)));
        sort($report->unusedFragments);
        ksort($report->hostComponents);

        return $report;
    }

    public static function isFragment(string $relative): bool
    {
        return array_intersect(explode('/', dirname($relative)), self::FRAGMENT_DIRECTORIES) !== [];
    }

    /** @return list<string> every `.mdx` under the root, relative and sorted */
    private function files(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'mdx') {
                $out[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($out);

        return $out;
    }

    /** @return array{0: ?string, 1: string} the frontmatter's inner text (null with none) and the body */
    private function split(string $source): array
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
                $legal = isset(LegalTexts::all()[basename($name)]);
                if (! $legal && ! isset($fragments[$name])) {
                    $problem ??= "it names the fragment {$name}, which no file under the content root defines";

                    continue;
                }
                $component = $legal ? LegalTexts::componentName(basename($name)) : Str::studly(basename($name));
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
     * it holds; a page whose meta has no such form is reported instead.
     *
     * @return array{0: ?string, 1: ?string} the frontmatter's inner text, and a problem
     */
    private function frontmatter(?string $frontmatter, ?string $meta, ?array $metaData): array
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
