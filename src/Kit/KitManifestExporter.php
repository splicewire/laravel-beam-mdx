<?php

namespace Splicewire\Beam\Mdx\Kit;

/**
 * Projects the PHP Blockdoc kit manifest into the compact shape consumed by the MDX editor package.
 */
final class KitManifestExporter
{
    /** @return array{profile: string, version: int, nodes: list<array<string, mixed>>} */
    public function manifest(): array
    {
        $source = (new MdxKitNodeSource)->manifest('content');

        $nodes = array_map(static function (array $node): array {
            $category = ($node['group'] ?? 'block') === 'inline'
                ? 'inline'
                : self::clientCategory($node['category'] ?? null);
            $admits = $node['admitsChildCategories'] ?? null;

            $clientNode = [
                'name' => $node['name'],
                'group' => $node['group'] ?? 'block',
                'category' => $category,
                'admitsChildCategories' => $admits === null
                    ? null
                    : array_map(self::clientCategory(...), $admits),
                'admitsText' => $node['admitsText'] ?? false,
                'attrsSchema' => $node['attrsSchema'],
            ];
            if (isset($node['description'])) {
                $clientNode['description'] = $node['description'];
            }
            if (in_array($node['name'], ['Steps', 'CardGrid'], true)) {
                $clientNode['x-editable'] = ['reorderable' => true];
            }

            return $clientNode;
        }, $source['nodes']);

        return [
            'profile' => 'beam-mdx-kit',
            'version' => $source['version'],
            'nodes' => $nodes,
        ];
    }

    private static function clientCategory(?string $category): ?string
    {
        return match ($category) {
            'html' => 'block',
            'card_grid' => 'card-grid',
            default => $category,
        };
    }
}
