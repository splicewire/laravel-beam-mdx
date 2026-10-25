<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Step`. */
#[NodeType('Step', description: 'One step in a Steps procedure; the title is the step heading.')]
final class Step extends Block
{
    public function category(): ?string
    {
        return 'step';
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
