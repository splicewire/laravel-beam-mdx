<?php

namespace Splicewire\Beam\Mdx\Legal;

use Illuminate\Support\Str;

/**
 * The four legal texts (ADR-0233 d8). Each was a one-paragraph MDX fragment, copied into every host that showed it and
 * identical in each once its frontmatter is set aside, placed with `<Content name="legal-engine" />`. They ship once,
 * here, as Authored Components named for their fragment, so a page places `<LegalEngine />` and the conversion command
 * rewrites the old tag to it.
 */
final class LegalTexts
{
    /** @var array<string, array{title: string, text: string}> */
    private const TEXTS = [
        'legal-ai-processing' => [
            'title' => 'AI Processing',
            'text' => 'The words, dates, and details you enter are sent to the AI providers that generate your results (currently OpenAI and Anthropic). They use your input only to produce what you asked for — never to train their models.',
        ],
        'legal-data-rights' => [
            'title' => 'Data Rights',
            'text' => "It's your data. Email us at the address in the footer to get a copy, export it, or delete it, and we'll take care of it.",
        ],
        'legal-engine' => [
            'title' => 'Platform',
            'text' => "This site runs on the Splicewire platform. Your data is kept isolated to this brand and isn't shared with the other sites that run on the same platform.",
        ],
        'legal-payments' => [
            'title' => 'Payments',
            'text' => 'Payments are handled by Stripe. Your card details go straight to Stripe; we never see or store your card number.',
        ],
    ];

    /** @return array<string, array{title: string, text: string}> by fragment name */
    public static function all(): array
    {
        return self::TEXTS;
    }

    /** The component a fragment becomes: its name in PascalCase, the tag a page places it by. */
    public static function componentName(string $fragment): string
    {
        return Str::studly($fragment);
    }

    /**
     * The component's body: its one paragraph, the root block declaring the component (ADR-0231 d8). It takes no props.
     *
     * @return list<array<string, mixed>>
     */
    public static function body(string $fragment): array
    {
        return [[
            'kind' => 'block', 'name' => 'p', 'isComponent' => false, 'dynamic' => false, 'props' => [],
            'children' => [['kind' => 'text', 'value' => self::TEXTS[$fragment]['text']]],
            'component' => ['name' => self::componentName($fragment), 'props' => []],
        ]];
    }
}
