<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `CardGrid`. */
#[NodeType('CardGrid', description: 'A responsive grid of Cards.')]
final class CardGrid extends Block
{
    public function category(): ?string
    {
        return 'card-grid';
    }

    public function admitsChildCategories(): ?array
    {
        return ['card'];
    }

    /** @return array<string, mixed> */
    public static function attrsSchema(): array
    {
        return ['type' => 'object'];
    }
}
