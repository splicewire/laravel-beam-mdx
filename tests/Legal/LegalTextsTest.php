<?php

namespace Splicewire\Beam\Mdx\Tests\Legal;

use Splicewire\Beam\Mdx\Legal\LegalTexts;
use Splicewire\Beam\Mdx\Tests\TestCase;

/**
 * ADR-0233 d8 (EP-27 slice 1): the four legal fragments every host copied, shipped once. Each is one paragraph, the
 * body of an Authored Component named for its fragment, so `<Content name="legal-engine" />` becomes `<LegalEngine />`.
 */
class LegalTextsTest extends TestCase
{
    public function test_each_legal_text_is_one_paragraph_component_named_for_its_fragment(): void
    {
        $this->assertSame(['legal-ai-processing', 'legal-data-rights', 'legal-engine', 'legal-payments'], array_keys(LegalTexts::all()));

        foreach (LegalTexts::all() as $fragment => $text) {
            $body = LegalTexts::body($fragment);
            $this->assertCount(1, $body, "{$fragment}: one root block");
            $this->assertSame(['p', LegalTexts::componentName($fragment)], [$body[0]['name'], $body[0]['component']['name']]);
            $this->assertSame([['kind' => 'text', 'value' => $text['text']]], $body[0]['children']);
        }
        $this->assertSame('LegalAiProcessing', LegalTexts::componentName('legal-ai-processing'));
    }
}
