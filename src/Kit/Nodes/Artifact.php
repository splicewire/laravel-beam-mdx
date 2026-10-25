<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Artifact`. */
#[NodeType('Artifact', description: 'Inline a committed, build-highlighted artifact file (opaque in the editor).')]
final class Artifact extends Block
{
    public function category(): ?string
    {
        return 'html';
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
                'file' => [
                    'type' => 'string',
                    'title' => 'Artifact file',
                ],
                'title' => [
                    'type' => 'string',
                    'title' => 'Title',
                ],
            ],
            'required' => ['file'],
        ];
    }
}
