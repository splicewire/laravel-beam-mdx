<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Card`. */
#[NodeType('Card', description: 'A linked card: href + title + blurb + icon.')]
final class Card extends Block
{
    public function category(): ?string
    {
        return 'card';
    }

    public function admitsChildCategories(): ?array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public static function attrsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'href' => [
                    'type' => 'string',
                    'title' => 'Link',
                ],
                'title' => [
                    'type' => 'string',
                    'title' => 'Title',
                ],
                'blurb' => [
                    'type' => 'string',
                    'title' => 'Blurb',
                ],
                'icon' => [
                    'type' => 'string',
                    'x-expression' => true,
                ],
            ],
        ];
    }
}
