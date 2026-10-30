<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `Ref`. */
#[NodeType('Ref', description: 'An inline evidence citation: <Ref to="key">the claim</Ref>.', group: 'inline')]
final class Ref extends Block
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
                'to' => [
                    'type' => 'string',
                    'title' => 'Reference key',
                ],
            ],
            'required' => ['to'],
        ];
    }
}
