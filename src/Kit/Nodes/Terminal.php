<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Terminal`. */
#[NodeType('Terminal', description: 'A terminal / code window with chrome and an optional title.')]
final class Terminal extends Block
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
                'title' => [
                    'type' => 'string',
                    'title' => 'Title',
                ],
            ],
        ];
    }
}
