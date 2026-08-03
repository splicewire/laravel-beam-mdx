<?php

namespace Splicewire\Beam\Mdx\Support\Anchors;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class AnchorStrategyFactory
{
    /**
     * Select an Anchor Strategy from the import's `source_type`. A configured
     * `sectioned` source_type yields a SectionedAnchorStrategy (falling back to
     * `paged` if it cannot vouch for any section); anything else is `paged`.
     */
    public static function make(?string $sourceType, string $linearText, PageMap $pageMap): AnchorStrategy
    {
        $config = $sourceType
            ? Config::get('anchors.strategies.'.Str::slug($sourceType))
            : null;

        if (is_array($config) && ($config['type'] ?? null) === 'sectioned' && ! empty($config['marker'])) {
            $strategy = new SectionedAnchorStrategy($pageMap, $linearText, $config['marker']);
            if ($strategy->isViable()) {
                return $strategy;
            }
        }

        return new PagedAnchorStrategy($pageMap);
    }
}
