<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `FileTree`. */
#[NodeType('FileTree', description: 'A file-structure tree (a `tree` node array or children).')]
final class FileTree extends Block
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
                'tree' => [
                    'type' => 'string',
                    'x-expression' => true,
                ],
            ],
        ];
    }
}
