<?php

namespace Splicewire\Beam\Mdx\Kit;

use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\Blockdoc\Schema\ManifestBuilder;
use Splicewire\Beam\Mdx\Kit\Nodes\Artifact;
use Splicewire\Beam\Mdx\Kit\Nodes\Callout;
use Splicewire\Beam\Mdx\Kit\Nodes\Card;
use Splicewire\Beam\Mdx\Kit\Nodes\CardGrid;
use Splicewire\Beam\Mdx\Kit\Nodes\DoctorOutput;
use Splicewire\Beam\Mdx\Kit\Nodes\Figure;
use Splicewire\Beam\Mdx\Kit\Nodes\FileTree;
use Splicewire\Beam\Mdx\Kit\Nodes\Ref;
use Splicewire\Beam\Mdx\Kit\Nodes\SectionLanding;
use Splicewire\Beam\Mdx\Kit\Nodes\Step;
use Splicewire\Beam\Mdx\Kit\Nodes\Steps;
use Splicewire\Beam\Mdx\Kit\Nodes\Terminal;

/**
 * ux-0233 d7 — the beam-mdx kit declared on the server: each `@splicewire/beam-mdx/kit` component as a `#[NodeType]`
 * Code Component, so an MDX page's kit tags are checked against the Manifest Profile like any other block, and the
 * kit's local `KIT_MANIFEST` copy retires. The kit components sit in the HTML profile's `html` category, so they
 * place wherever HTML does; `Step` and `Card` take their own categories so only `Steps` and `CardGrid` hold them.
 *
 * `Content` is not here: a fragment is an Authored Component placed by its own tag (d8), and `Content` leaves the kit.
 */
final class MdxKitNodeSource implements ManifestNodeSource
{
    /** @var list<class-string<Block>> */
    public const NODES = [
        Callout::class,
        Steps::class,
        Step::class,
        Figure::class,
        Terminal::class,
        SectionLanding::class,
        CardGrid::class,
        Card::class,
        DoctorOutput::class,
        FileTree::class,
        Ref::class,
        Artifact::class,
    ];

    public function profiles(): array
    {
        return ['content'];
    }

    public function nodeClassesFor(string $profile): array
    {
        if ($profile !== 'content') {
            return [];
        }

        // Artifact is rendered by the host (the flagship's highlighter plugin), not by beam-mdx/kit: a host without it
        // turns `beam.mdx.kit.artifact` off, so its MDX pages never declare a component its registry cannot draw.
        return config('beam.mdx.kit.artifact', true)
            ? self::NODES
            : array_values(array_filter(self::NODES, fn (string $class) => $class !== Artifact::class));
    }

    public function docAdmitsFor(string $profile): ?array
    {
        return ['html'];
    }

    public function marksFor(string $profile): array
    {
        return [];
    }

    /**
     * The kit's manifest nodes. Each component's props are its declared `attrsSchema()`: node identity lives on the
     * JsonNode, never among a component's props, so the builder's default `id` attribute is not one of them.
     *
     * @return array<string, mixed>
     */
    public function manifest(string $profile): array
    {
        $manifest = (new ManifestBuilder($this))->build($profile);
        foreach ($this->nodeClassesFor($profile) as $index => $class) {
            /** @var class-string<Block>&callable-string $class */
            $manifest['nodes'][$index]['attrsSchema'] = $class::attrsSchema();
        }

        return $manifest;
    }
}
