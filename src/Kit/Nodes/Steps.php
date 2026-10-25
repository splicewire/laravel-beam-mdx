<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Steps`. */
#[NodeType('Steps', description: 'A numbered procedure — a list of Step children.')]
final class Steps extends Block
{
    public function category(): ?string
    {
        return 'html';
    }

    public function admitsChildCategories(): ?array
    {
        return ['step'];
    }

    /** @return array<string, mixed> */
    public static function attrsSchema(): array
    {
        return ['type' => 'object'];
    }
}
