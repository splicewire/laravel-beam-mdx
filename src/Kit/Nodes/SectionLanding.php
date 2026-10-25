<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `SectionLanding`. */
#[NodeType('SectionLanding', description: 'A landing hero: eyebrow + heading + lede over a CardGrid.')]
final class SectionLanding extends Block
{
    public function category(): ?string
    {
        return 'html';
    }

    public function admitsChildCategories(): ?array
    {
        return ['card-grid', 'html'];
    }

    /** @return array<string, mixed> */
    public static function attrsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'eyebrow' => [
                    'type' => 'string',
                    'title' => 'Eyebrow',
                ],
                'heading' => [
                    'type' => 'string',
                    'title' => 'Heading',
                ],
                'lede' => [
                    'type' => 'string',
                    'x-expression' => true,
                ],
            ],
            'required' => ['heading'],
        ];
    }
}
