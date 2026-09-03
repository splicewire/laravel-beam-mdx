<?php

namespace Splicewire\Beam\Mdx\Tests\Frontmatter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Splicewire\Beam\Mdx\MdxBody;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * What `encode()`/`decode()` actually guarantee, at the ends of the document and inside the fence
 * (beam-docs-satellite ticket 70).
 *
 * The docblock on {@see MdxBody} used to say only *"the suite asserts the byte-for-byte round-trip"*,
 * and the one assertion behind that sentence — `MdxReaderCollapseTest` — used a fixture with no
 * terminal blank line, no quoted value, no CRLF and no blank line inside the fence. So the claim was
 * broader than the measurement in four directions at once: the estate's *"the check succeeded and
 * tested something else"* shape landing on a **guarantee** rather than on a defect.
 *
 * Ticket 70 was opened believing the ends were where it broke — that a save strips one leading and one
 * trailing newline from `content`. That is **false at this layer** and the first four cases below are
 * the measurement: `content` round-trips byte-for-byte including both terminal newlines, and it does so
 * whether or not the source carries a frontmatter block. (It is false one layer up too — an
 * `EntryBodySaveOp::handle()` → `EntryBodyShowOp::handle()` pair over `"\n# Body\n\n"` returns it
 * unchanged.) Wherever the flagship's two bytes went, it was not here.
 *
 * The three real losses are all **inside the fence**, and they are losses by construction rather than
 * by accident: the fence is parsed into a `key => value` map and re-emitted from it, so anything the
 * map cannot carry — the author's line endings, their quoting, their blank and comment lines — is gone
 * by the time `decode()` runs. That asymmetry is the doctrine
 * (`scriptorium-corpus/apocrypha/data-entry-surface-is-a-lossy-projection.md`: forward is total,
 * backward is best-effort over a narrower representation), not a bug queued for later. It is pinned
 * here so a future reader meets the boundary as a decision instead of rediscovering it as a diff.
 */
class MdxBodyRoundTripTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function preservedSources(): array
    {
        return [
            'a leading blank line' => ["---\ntitle: A\n---\n\n# Body\n"],
            'a trailing blank line' => ["---\ntitle: A\n---\n# Body\n\n"],
            'both ends blank' => ["---\ntitle: A\n---\n\n# Body\n\n"],
            'no frontmatter at all' => ["\n# Body\n\n"],
            'no trailing newline' => ["---\ntitle: A\n---\n# Body"],
            'the flagship shape — a JSX comment opening the body' => ["---\ntitle: A\n---\n\n{/* c */}\n\n<X />\n\n"],
        ];
    }

    /**
     * The assertion ticket 70's acceptance asked for. It is GREEN on the source it was written
     * against, which is the ticket's answer: the two bytes are not lost in this codec.
     */
    #[Test]
    #[DataProvider('preservedSources')]
    public function content_round_trips_byte_for_byte_including_both_terminal_newlines(string $raw): void
    {
        $this->assertSame($raw, MdxBody::decode(MdxBody::encode($raw)));
    }

    /**
     * `PlacedDiskMirror` writes `decode()`'s output to a git-tracked file, so this is the property that
     * makes the mirror reviewable: saving a body you did not edit must produce no diff. Asserted as
     * idempotence rather than as equality with the source, because that is what a reviewer sees.
     */
    #[Test]
    public function a_second_round_trip_changes_nothing_a_first_one_did_not(): void
    {
        $raw = "---\ntitle: A\n---\n\n# Body\n\n";

        $once = MdxBody::decode(MdxBody::encode($raw));
        $twice = MdxBody::decode(MdxBody::encode($once));

        $this->assertSame($once, $twice);
    }

    /**
     * Three losses, all inside the fence. Each one is a silent rewrite of the author's file the next
     * time an entry is saved — the exact harm {@see MdxBody::split()}'s docblock is written to prevent
     * — and each is out of reach of that defence, because the defence preserves the authored KEY and
     * the fence carries more than keys.
     */
    #[Test]
    public function the_frontmatter_fence_is_normalized_and_the_normalization_is_declared(): void
    {
        // CRLF: the block regex admits `\r\n`, `decode()` emits `\n`. A file authored on Windows comes
        // back with its fence rewritten (the BODY's line endings survive — only the fence is re-emitted).
        $this->assertSame(
            "---\ntitle: A\n---\n# Body\r\n",
            MdxBody::decode(MdxBody::encode("---\r\ntitle: A\r\n---\r\n# Body\r\n")),
        );

        // Quoting: `FrontmatterParser` trims `" \t\"'` off the value, so the quotes an author typed are
        // not part of what `raw` holds and cannot be re-emitted.
        $this->assertSame(
            "---\ntitle: A\n---\n# Body\n",
            MdxBody::decode(MdxBody::encode("---\ntitle: \"A\"\n---\n# Body\n")),
        );

        // Anything that is not a `key: value` line — a blank line, a comment, a nested block — is
        // dropped rather than preserved, because the parser's product is a map.
        $this->assertSame(
            "---\ntitle: A\nother: B\n---\n# Body\n",
            MdxBody::decode(MdxBody::encode("---\ntitle: A\n\nother: B\n---\n# Body\n")),
        );
    }

    /**
     * The half of the guarantee that IS byte-for-byte inside the fence, and the reason `split()` returns
     * `raw`: an author's `navOrder:` must not come back as `nav_order:`. Held here against the fence
     * cases above so the two are read together rather than as a contradiction.
     */
    #[Test]
    public function the_authored_key_spelling_survives_the_normalization(): void
    {
        $this->assertSame(
            "---\nnavOrder: 2\nnav-group: K\n---\nbody\n",
            MdxBody::decode(MdxBody::encode("---\nnavOrder: 2\nnav-group: K\n---\nbody\n")),
        );
    }
}
