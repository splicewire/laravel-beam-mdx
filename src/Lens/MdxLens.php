<?php

namespace Splicewire\Beam\Mdx\Lens;

/**
 * ux-0233 d2 — the MDX Lens: an MDX page body read into an id-less JsonDoc, and a JsonDoc printed back as canonical
 * MDX. The lens is `@splicewire/beam-mdx/lens`, a Node concern ({@see NodeMdxLens} shells out to it), so it runs on
 * ADR-0228 decision 9's three server paths as the TSX Lens does. The body carries no frontmatter, which is the
 * page's settings (d3), and the returned doc carries no ids: the caller reattaches the Working Head's by content.
 */
interface MdxLens
{
    /**
     * Read an MDX body into blocks. `$context` is the TSX Lens's: a resolved Manifest Profile (`profile`), and the
     * enclosing component when the body is a component entry's (`component`).
     *
     * @param  array{profile?: array<string, mixed>, component?: string}  $context
     * @return array{doc: array<int, array<string, mixed>>, meta: string|null} `meta` is schemastud's
     *                                                                         `export const meta = {…}` source, if any
     */
    public function read(string $body, array $context = []): array;

    /**
     * Print blocks as canonical MDX: Markdown wherever Markdown can say it, one line per paragraph, JSX otherwise.
     *
     * @param  array<int, array<string, mixed>>  $doc
     */
    public function print(array $doc): string;
}
