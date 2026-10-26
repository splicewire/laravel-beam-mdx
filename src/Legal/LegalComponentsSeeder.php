<?php

namespace Splicewire\Beam\Mdx\Legal;

use Illuminate\Database\Seeder;
use Splicewire\Beam\Ux\Seed\SeedsEntries;

/**
 * Seeds the four legal texts as package-origin Authored Components (ADR-0233 d8, with ADR-0213 and ADR-0215): born
 * published, re-asserted from this package while pristine, a site's own edit kept. It uses laravel-beam-ux's seeding,
 * so it is registered only where laravel-beam-ux is installed (which requires this package, never the reverse).
 */
class LegalComponentsSeeder extends Seeder
{
    use SeedsEntries;

    public const NAMESPACE = 'beam-mdx';

    public function run(): void
    {
        foreach (LegalTexts::all() as $fragment => $text) {
            $this->seedComponent($fragment, LegalTexts::body($fragment), ['title' => $text['title']],
                namespace: self::NAMESPACE, origin: 'package:splicewire/laravel-beam-mdx');
        }
    }
}
