<?php

namespace Splicewire\Beam\Mdx\Kit\Nodes;

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;

/** ux-0233 d7: a beam-mdx kit Code Component, rendered by `@splicewire/beam-mdx/kit`'s `DoctorOutput`. */
#[NodeType('DoctorOutput', description: 'A structured doctor / diagnostic report (raw text or children).')]
final class DoctorOutput extends Block
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
                'raw' => [
                    'type' => 'string',
                    'title' => 'Raw output',
                ],
            ],
        ];
    }
}
