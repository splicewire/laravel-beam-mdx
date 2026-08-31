<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\Mdx;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * `Mdx` now reads through the shared grammar (ticket 04, step 1) — and its PUBLIC contract is
 * unchanged, which is the whole point of the step.
 *
 * ⚠️ `Mdx::fields()` returns the AUTHORED keys, not the canonical ones. `splicewire/tower`'s
 * `src/Navigation/Docs/DocsGuides.php:74-77` reads `navGroup`, `navOrder`, `navGroupOrder` and
 * `navParent` — camelCase — straight off this method, behind `??` defaults that would swallow a miss
 * silently. Canonicalizing here would break tower's docs nav with no error anywhere.
 *
 * This is why {@see \Splicewire\Beam\Mdx\Frontmatter\ParsedFrontmatter} carries both key sets: the
 * collapse is behaviour-preserving because `raw` exists. Changing this method's contract is a separate,
 * declared act with tower's reader in the same change.
 */
class MdxReaderCollapseTest extends TestCase
{
    private function seedGuide(): string
    {
        $root = sys_get_temp_dir().'/beam-mdx-collapse-'.getmypid().'-'.uniqid();
        @mkdir($root.'/docs', 0777, true);
        file_put_contents(
            $root.'/docs/guide.mdx',
            "---\ntitle: A guide\nnavGroup: Knowledge\nnavOrder: 2\nnavParent: docs/index\naccess: pro\n---\n# Body\n"
        );
        config()->set('beam.mdx.content_path', $root);

        return $root;
    }

    #[Test]
    public function fields_still_returns_the_authored_spelling(): void
    {
        $this->seedGuide();

        $fields = Mdx::fields('docs/guide');

        // The four keys tower reads, in the spelling tower reads them.
        $this->assertSame('Knowledge', $fields['navGroup'] ?? null);
        $this->assertSame('2', $fields['navOrder'] ?? null);
        $this->assertSame('docs/index', $fields['navParent'] ?? null);
        $this->assertSame('A guide', $fields['title'] ?? null);

        // And explicitly NOT the canonical spelling — a regression toward canonicalizing here would
        // pass every other test in this suite and break tower silently.
        $this->assertArrayNotHasKey('nav_group', $fields);
        $this->assertArrayNotHasKey('nav_order', $fields);
    }

    #[Test]
    public function mdxbody_round_trips_the_authored_spelling_byte_for_byte(): void
    {
        // encode/decode are declared inverses and encode's output is PERSISTED into the particle
        // body. Canonicalizing there would make decode re-emit `nav_order:` over an author's
        // `navOrder:` — a silent rewrite of their file on the next round-trip. This is the assertion
        // that forbids it.
        $raw = "---\ntitle: A guide\nnavGroup: Knowledge\nnavOrder: 2\n---\n# Body\n";

        $this->assertSame($raw, \Splicewire\Beam\Mdx\MdxBody::decode(\Splicewire\Beam\Mdx\MdxBody::encode($raw)));
    }

    #[Test]
    public function mdxbody_stores_the_authored_keys_not_the_canonical_ones(): void
    {
        $encoded = \Splicewire\Beam\Mdx\MdxBody::encode("---\nnavOrder: 2\n---\nbody\n");
        $frontmatter = $encoded[\Splicewire\Beam\Mdx\MdxBody::FRONTMATTER_KEY];

        $this->assertArrayHasKey('navOrder', $frontmatter);
        $this->assertArrayNotHasKey('nav_order', $frontmatter);
    }

    #[Test]
    public function the_single_word_gates_are_unaffected(): void
    {
        $this->seedGuide();

        // `access`/`draft`/`entitlement` have no word boundary, so canonicalization is a no-op for
        // them either way — asserted so the collapse is shown not to have moved them.
        $this->assertSame(['pro'], Mdx::gate('docs/guide'));
    }
}
