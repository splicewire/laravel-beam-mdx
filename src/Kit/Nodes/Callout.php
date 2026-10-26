<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Callout`. */
#[NodeType('Callout', description: 'An admonition (note / tip / warning / danger / info) with an optional title.')]
final class Callout extends Block
{
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
                'type' => [
                    'type' => 'string',
                    'enum' => ['note', 'tip', 'warning', 'danger', 'info'],
                    'default' => 'note',
                    'title' => 'Kind',
                ],
                'title' => [
                    'type' => 'string',
                    'title' => 'Title',
                ],
            ],
        ];
    }
}
