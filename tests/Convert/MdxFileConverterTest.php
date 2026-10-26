<?php

namespace Splicewire\Beam\Mdx\Tests\Convert;

use Illuminate\Support\Str;
use Splicewire\Beam\Mdx\Convert\MdxFileConverter;
use Splicewire\Beam\Mdx\Doctor\UnusedFragmentAudit;
use Splicewire\Beam\Mdx\Lens\MdxLens;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * ADR-0233 d8, d9 (EP-27 slice 2; rulings 1, 4): a host's MDX files convert once, in one reviewed commit. Each page is
 * read through the lens and printed canonically: `<Content name="fragments/legal-engine" />` becomes the shipped
 * `<LegalEngine />`, a host's own fragment becomes its component's tag, and a static `export const meta` becomes
 * frontmatter. A page whose blocks or visible text would change is left out and reported, and so is one whose meta has
 * no frontmatter form or that names a fragment no file defines. A fragment no page includes is reported, not converted.
 */
class MdxFileConverterTest extends TestCase
{
    private string $root;

    private FakeLens $lens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/mdx-convert-'.Str::random(8);
        $this->lens = new FakeLens;
        $this->app->instance(MdxLens::class, $this->lens);
        $this->file('fragments/legal-engine.mdx', "---\ntitle: Platform\n---\n\nThis site runs on the Splicewire platform.\n");
        $this->file('fragments/reading-disclaimer.mdx', "Read this first.\n");
        $this->file('fragments/advisory.mdx', "Not advice.\n");
    }

    protected function tearDown(): void
    {
        // Scratch fixtures under the system temp dir, made by this test: moved aside, never deleted.
        if (is_dir($this->root)) {
            rename($this->root, $this->root.'.done');
        }
        parent::tearDown();
    }

    public function test_a_page_s_fragments_become_component_tags_and_its_meta_becomes_frontmatter(): void
    {
        $about = $this->page('about.mdx', "---\ntitle: About\n---\n\n", "Intro.\n\n<Content name=\"fragments/legal-engine\" />\n\n<Content name=\"fragments/reading-disclaimer\" />\n",
            [$this->p('Intro.'), $this->content('fragments/legal-engine'), $this->content('fragments/reading-disclaimer')]);
        $start = $this->page('docs/start.mdx', '', "export const meta = { title: 'Start', order: 0 }\n\n# Start\n", [$this->block('h1', [$this->text('Start')])],
            meta: "export const meta = { title: 'Start', order: 0 }", metaData: ['title' => 'Start', 'order' => 0]);

        $report = app(MdxFileConverter::class)->convert($this->root, write: true);

        $this->assertSame(['about.mdx', 'docs/start.mdx'], $report->converted);
        $this->assertSame(['about.mdx' => ['LegalEngine']], $report->legal);
        $this->assertSame(['fragments/reading-disclaimer' => 'ReadingDisclaimer'], $report->hostComponents);
        $this->assertSame(['fragments/advisory'], $report->unusedFragments);
        $this->assertSame([], $report->leftOut);

        $this->assertSame("---\ntitle: About\n---\n\n".$this->lens->printed([$this->p('Intro.'), $this->tag('LegalEngine'), $this->tag('ReadingDisclaimer')]), file_get_contents($about));
        $this->assertStringStartsWith("---\ntitle: \"Start\"\norder: 0\n---\n\n", file_get_contents($start), 'the static meta as frontmatter');
        $this->assertSame("Not advice.\n", file_get_contents($this->root.'/fragments/advisory.mdx'), 'a fragment is never rewritten');
    }

    public function test_a_page_that_would_change_or_cannot_convert_is_left_out_and_reported(): void
    {
        $this->page('dynamic.mdx', '', "export const meta = { title: name }\n\n# Hi\n", [$this->block('h1', [$this->text('Hi')])],
            meta: 'export const meta = { title: name }', metaData: null);
        $this->page('nested.mdx', '', "export const meta = { tags: ['a'] }\n\n# Hi\n", [$this->block('h1', [$this->text('Hi')])],
            meta: "export const meta = { tags: ['a'] }", metaData: ['tags' => ['a']]);
        $this->page('missing.mdx', '', "<Content name=\"fragments/nowhere\" />\n", [$this->content('fragments/nowhere')]);
        $lossy = $this->page('lossy.mdx', '', "Keep me.\n", [$this->p('Keep me.')]);
        $this->lens->lossy = 'Keep me.';
        $original = file_get_contents($lossy);

        $report = app(MdxFileConverter::class)->convert($this->root, write: true);

        $this->assertSame([], $report->converted);
        $this->assertSame(['dynamic.mdx', 'lossy.mdx', 'missing.mdx', 'nested.mdx'], array_keys($report->leftOut));
        $this->assertStringContainsString('meta', $report->leftOut['dynamic.mdx']);
        $this->assertStringContainsString('fragments/nowhere', $report->leftOut['missing.mdx']);
        $this->assertStringContainsString('tags', $report->leftOut['nested.mdx']);
        $this->assertStringContainsString('would change', $report->leftOut['lossy.mdx']);
        $this->assertSame($original, file_get_contents($lossy), 'a left-out page is untouched');
    }

    public function test_a_dry_run_writes_nothing_and_a_canonical_page_is_unchanged(): void
    {
        $about = $this->page('about.mdx', '', "<Content name=\"fragments/legal-engine\" />\n", [$this->content('fragments/legal-engine')]);
        $canonical = $this->page('plain.mdx', '', "Already canonical.\n", [$this->p('Already canonical.')]);
        $this->lens->register("Already canonical.\n", [$this->p('Already canonical.')]);
        $before = file_get_contents($about);

        $this->artisan('splicewire:beam:mdx:convert', ['--path' => $this->root, '--dry-run' => true])
            ->expectsOutputToContain('Would convert 1 page')
            ->expectsOutputToContain('about.mdx')
            ->expectsOutputToContain('fragments/advisory')
            ->assertExitCode(0);

        $this->assertSame($before, file_get_contents($about));
        $report = app(MdxFileConverter::class)->convert($this->root, write: false);
        $this->assertSame(['plain.mdx'], $report->unchanged);
        $this->assertSame("Already canonical.\n", file_get_contents($canonical));
    }

    public function test_the_doctor_names_a_fragment_no_page_includes(): void
    {
        $this->page('about.mdx', '', "<Content name=\"fragments/legal-engine\" />\n<Content name=\"fragments/reading-disclaimer\" />\n", []);
        config(['beam.mdx.content_path' => $this->root]);

        $finding = app(UnusedFragmentAudit::class)->run()[0];

        $this->assertSame('warn', $finding->status->value);
        $this->assertStringContainsString('fragments/advisory', $finding->detail);
        $this->assertStringNotContainsString('legal-engine', $finding->detail);
    }

    private function page(string $path, string $frontmatter, string $body, array $doc, ?string $meta = null, ?array $metaData = null): string
    {
        $this->lens->register($body, $doc, $meta, $metaData);

        return $this->file($path, $frontmatter.$body);
    }

    private function file(string $path, string $contents): string
    {
        @mkdir(dirname($this->root.'/'.$path), 0777, true);
        file_put_contents($this->root.'/'.$path, $contents);

        return $this->root.'/'.$path;
    }

    private function content(string $name): array
    {
        return $this->block('Content', [], [['name' => 'name', 'kind' => 'string', 'value' => $name]], true);
    }

    private function tag(string $name): array
    {
        return $this->block($name, [], [], true);
    }

    private function p(string $text): array
    {
        return $this->block('p', [$this->text($text)]);
    }

    private function block(string $name, array $children = [], array $props = [], bool $component = false): array
    {
        return ['kind' => 'block', 'name' => $name, 'isComponent' => $component, 'props' => $props, 'children' => $children, 'dynamic' => false];
    }

    private function text(string $value): array
    {
        return ['kind' => 'text', 'value' => $value];
    }
}

/** An in-memory lens: original bodies read as registered, and anything it prints reads back exactly. */
final class FakeLens implements MdxLens
{
    /** @var array<string, array{doc: array, meta: ?string, metaData: ?array}> */
    private array $reads = [];

    /** A text this lens drops when printing, to stand for a print that loses content. */
    public ?string $lossy = null;

    public function register(string $body, array $doc, ?string $meta = null, ?array $metaData = null): void
    {
        $this->reads[$body] = ['doc' => $doc, 'meta' => $meta, 'metaData' => $metaData];
    }

    public function read(string $body, array $context = []): array
    {
        if (str_starts_with($body, 'PRINTED ')) {
            return ['doc' => json_decode(trim(substr($body, 8)), true), 'meta' => null, 'metaData' => null];
        }

        return $this->reads[$body] ?? throw new \RuntimeException('FakeLens: unregistered body '.json_encode($body));
    }

    public function print(array $doc): string
    {
        if ($this->lossy !== null) {
            $kept = array_values(array_filter($doc, fn ($node) => ($node['children'][0]['value'] ?? null) !== $this->lossy));
            if ($kept !== $doc) {
                return $this->printed($kept);
            }
        }
        foreach ($this->reads as $body => $read) {
            if ($read['doc'] === $doc && $read['meta'] === null) {
                return $body;
            }
        }

        return $this->printed($doc);
    }

    public function printed(array $doc): string
    {
        return 'PRINTED '.json_encode($doc)."\n";
    }
}
