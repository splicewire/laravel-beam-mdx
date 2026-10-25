<?php

namespace Splicewire\Beam\Mdx\Tests\Kit;

use Schemastud\Blockdoc\Attributes\NodeType;
use Splicewire\Beam\Mdx\Kit\MdxKitNodeSource;
use Splicewire\Beam\Mdx\Tests\TestCase;

/** ux-0233 d7 — the beam-mdx kit as `#[NodeType]` Code Components, the server's declaration of its vocabulary. */
final class MdxKitNodeSourceTest extends TestCase
{
    public function test_the_kit_is_declared_as_node_types_named_for_their_tags_and_content_has_left_it(): void
    {
        $manifest = (new MdxKitNodeSource)->manifest('content');
        $names = array_column($manifest['nodes'], 'name');

        $this->assertSame([
            'Callout', 'Steps', 'Step', 'Figure', 'Terminal', 'SectionLanding', 'CardGrid', 'Card',
            'DoctorOutput', 'FileTree', 'Ref', 'Artifact',
        ], $names);
        $this->assertNotContains('Content', $names, 'a fragment is an Authored Component placed by its own tag (d8)');

        foreach (MdxKitNodeSource::NODES as $class) {
            $attribute = (new \ReflectionClass($class))->getAttributes(NodeType::class)[0]->newInstance();
            $this->assertSame((new \ReflectionClass($class))->getShortName(), $attribute->name, "{$class} is named for its JSX tag");
        }
    }

    public function test_the_kit_places_where_html_does_and_its_own_categories_hold_steps_and_cards(): void
    {
        $nodes = array_column((new MdxKitNodeSource)->manifest('content')['nodes'], null, 'name');

        $this->assertSame('html', $nodes['Callout']['category']);
        $this->assertSame('inline', $nodes['Ref']['group']);
        $this->assertSame(['step'], $nodes['Steps']['admitsChildCategories']);
        $this->assertSame('step', $nodes['Step']['category']);
        $this->assertSame(['card'], $nodes['CardGrid']['admitsChildCategories']);
        $this->assertSame([], $nodes['Card']['admitsChildCategories']);
        $this->assertSame(['card_grid', 'html'], $nodes['SectionLanding']['admitsChildCategories']);
        $this->assertNull($nodes['Callout']['admitsChildCategories']);
        $this->assertSame([], $nodes['Artifact']['admitsChildCategories']);
    }

    public function test_a_components_props_are_its_declared_schema_without_a_node_id(): void
    {
        $nodes = array_column((new MdxKitNodeSource)->manifest('content')['nodes'], null, 'name');

        $this->assertSame(['type', 'title'], array_keys($nodes['Callout']['attrsSchema']['properties']));
        $this->assertSame(['note', 'tip', 'warning', 'danger'], $nodes['Callout']['attrsSchema']['properties']['type']['enum']);
        $this->assertSame(['heading'], $nodes['SectionLanding']['attrsSchema']['required']);
        $this->assertTrue($nodes['Card']['attrsSchema']['properties']['icon']['x-expression']);
        $this->assertSame(['to'], $nodes['Ref']['attrsSchema']['required']);
        foreach ($nodes as $name => $node) {
            $this->assertArrayNotHasKey('id', $node['attrsSchema']['properties'] ?? [], "{$name}'s identity lives on the JsonNode");
        }
    }
}
