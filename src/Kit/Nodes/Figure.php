<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Figure`. */
#[NodeType('Figure', description: 'A captioned image or inline diagram.')]
final class Figure extends Block
{
    public function admitsText(): bool
    {
        return true;
    }

    public function category(): ?string
    {
        return 'html';
    }

    public function admitsChildCategories(): ?array
    {
        return null;
    }

    /** @return array<string, mixed> */
    public static function attrsSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'src' => [
                    'type' => 'string',
                    'title' => 'Image URL',
                ],
                'alt' => [
                    'type' => 'string',
                    'title' => 'Alt text',
                ],
                'caption' => [
                    'type' => 'string',
                    'title' => 'Caption',
                ],
            ],
        ];
    }
}
